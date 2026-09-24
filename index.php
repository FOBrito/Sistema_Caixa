<?php
/**
 * index.php
 * VIEW PRINCIPAL (shell da aplicação)
 *
 * Este arquivo é a ÚNICA página real do site. Toda a navegação entre
 * módulos (home, caixa, barracas...) acontece via AJAX, trocando o
 * conteúdo da <div id="app">. A URL no navegador nunca muda.
 */

session_start();

define('APP_ROOT', __DIR__);

// Home também precisa consultar o banco (status de venda das barracas)
require_once APP_ROOT . '/config/database.php';

// Módulo que é carregado quando o usuário abre o site pela primeira vez
$moduloInicial = 'home';

/**
 * Adiciona "?v=<data de modificação do arquivo>" no final de um caminho
 * de asset (css/js). Isso invalida o cache do navegador automaticamente
 * sempre que o arquivo mudar — sem precisar lembrar de trocar nenhum
 * número de versão manualmente, e sem forçar recarregar tudo sempre
 * (só quando o arquivo realmente mudou).
 */
function versaoAsset(string $caminhoRelativo): string
{
    $caminhoAbsoluto = APP_ROOT . '/' . $caminhoRelativo;
    $versao = file_exists($caminhoAbsoluto) ? filemtime($caminhoAbsoluto) : time();
    return $caminhoRelativo . '?v=' . $versao;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paróquia Nossa Senhora da Guia — Controle de Gastos</title>
    <link rel="icon" href="img/brasao-linha.png" type="image/png">

    <!-- CSS global (variáveis, layout, componentes reutilizáveis: card, botões, form) -->
    <link rel="stylesheet" href="<?= versaoAsset('css/style.css') ?>">

    <!-- CSS específico do módulo atualmente carregado -->
    <link rel="stylesheet" href="<?= versaoAsset('modules/' . $moduloInicial . '/css/style.css') ?>" id="modulo-css">

    <!-- Biblioteca externa usada pelo leitor de QR Code (usada pelos módulos caixa e barracas) -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
</head>
<body>

    <!-- Cabeçalho persistente: fica visível em TODAS as telas, mesmo
         trocando de módulo via AJAX, porque fica fora da <div id="app"> -->
    <header class="topo-app">
        <img src="img/brasao.png" alt="Brasão da Paróquia Nossa Senhora da Guia" class="brasao-topo">
        <p class="topo-subtitulo">Paróquia Nossa Senhora da Guia — Aparecida de Goiânia-GO</p>
    </header>

    <main class="container">
        <div id="app">
            <?php
            // Primeiro carregamento: renderiza o módulo inicial direto no servidor
            // (evita 1 requisição AJAX extra logo na entrada do site)
            $viewInicial = APP_ROOT . '/modules/' . $moduloInicial . '/index.php';
            if (file_exists($viewInicial)) {
                include $viewInicial;
            } else {
                echo '<p>Módulo inicial não encontrado.</p>';
            }
            ?>
        </div>
    </main>

    <!-- JS global: núcleo da SPA (router via AJAX) -->
    <script src="<?= versaoAsset('js/app.js') ?>"></script>

    <!-- JS específico do módulo atualmente carregado -->
    <script src="<?= versaoAsset('modules/' . $moduloInicial . '/js/app.js') ?>" id="modulo-js"></script>
</body>
</html>
