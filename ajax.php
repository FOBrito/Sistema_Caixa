<?php
/**
 * ajax.php
 * CONTROLLER GLOBAL (Front Controller)
 *
 * Toda chamada AJAX do sistema passa por aqui. Existem 2 tipos de chamada:
 *
 *  1) NAVEGAÇÃO (troca de tela / módulo) -> retorna HTML
 *     GET ajax.php?modulo=caixa&acao=view
 *
 *  2) AÇÃO INTERNA de um módulo (salvar, buscar, etc) -> retorna JSON
 *     POST ajax.php?modulo=caixa&acao=cadastrar_recarregar
 *     -> delega para modules/caixa/api/cadastrar_recarregar.php
 *
 * Esse arquivo NÃO tem lógica de negócio. Ele só decide para qual
 * arquivo do módulo a requisição deve ser encaminhada (roteamento),
 * e protege as ações do caixa que exigem login.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0); // Mantenha 0 em produção (InfinityFree) para não vazar erro em tela

// Sessão usada pelo login (fixo, só senha) do módulo caixa
session_start();

define('APP_ROOT', __DIR__);

// ---------------------------------------------------------------
// 1) Lista branca de módulos permitidos (proteção contra path traversal)
//    -> sempre que criar um módulo novo, adicione o nome aqui.
// ---------------------------------------------------------------
$modulosPermitidos = ['home', 'caixa', 'barracas'];

$modulo = $_REQUEST['modulo'] ?? '';
$acao   = $_REQUEST['acao']   ?? 'view';

if (!in_array($modulo, $modulosPermitidos, true)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['erro' => 'Módulo inválido.']);
    exit;
}

// Sanitiza o nome da ação (só letras, números, _ e -) — evita ../../ etc.
$acao = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $acao);

$pastaModulo = APP_ROOT . '/modules/' . $modulo;

// ---------------------------------------------------------------
// 2) Ação "view" -> devolve o HTML (a View) do módulo, para ser
//    injetado na <div id="app"> pelo router em js/app.js.
//    A própria view do caixa decide se mostra o login ou o conteúdo,
//    então "view" nunca é bloqueada aqui.
// ---------------------------------------------------------------
if ($acao === 'view') {
    header('Content-Type: text/html; charset=UTF-8');

    $arquivoView = $pastaModulo . '/index.php';

    if (!file_exists($arquivoView)) {
        http_response_code(404);
        echo '<p class="mensagem">Módulo não encontrado.</p>';
        exit;
    }

    // Views também podem precisar do banco (ex: home e barracas
    // consultam se a venda está liberada antes de renderizar)
    require_once APP_ROOT . '/config/database.php';

    include $arquivoView;
    exit;
}

// ---------------------------------------------------------------
// 3) Portão de autenticação do módulo Caixa.
//    Só as ações que MEXEM ou LEEM dados sensíveis exigem login;
//    "login" (óbvio) e "logout" (sempre liberado) ficam de fora.
// ---------------------------------------------------------------
$acoesQueExigemLoginCaixa = ['cadastrar_recarregar', 'relatorio', 'exportar_excel', 'alternar_venda', 'devolucao'];

if ($modulo === 'caixa' && in_array($acao, $acoesQueExigemLoginCaixa, true)) {
    if (empty($_SESSION['caixa_autenticado'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['erro' => 'Sessão não autenticada. Faça login novamente.']);
        exit;
    }
}

// ---------------------------------------------------------------
// 4) Qualquer outra ação -> delega para o arquivo dentro de
//    modules/{modulo}/api/{acao}.php  (esse arquivo é o "Controller"
//    específico daquela ação, e ele fala com os Models do módulo)
//
//    Algumas ações geram uma SAÍDA PRÓPRIA (ex: download de arquivo),
//    então não fixamos Content-Type: application/json para elas.
// ---------------------------------------------------------------
$acoesComSaidaPropria = ['exportar_excel'];

if (!in_array($acao, $acoesComSaidaPropria, true)) {
    header('Content-Type: application/json; charset=UTF-8');
}

$arquivoApi = $pastaModulo . '/api/' . $acao . '.php';

if ($acao === '' || !file_exists($arquivoApi)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['erro' => 'Ação não encontrada.']);
    exit;
}

// Disponibiliza a conexão com o banco e a senha do caixa para quem precisar
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/config/auth.php';

include $arquivoApi;
