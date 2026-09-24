<?php
/**
 * config/database.php
 * Conexão única com o banco de dados (MySQL), compartilhada por todos
 * os módulos. Usa PDO (nativo do PHP, funciona no InfinityFree sem
 * precisar de nenhuma extensão extra).
 *
 * Este arquivo é incluído automaticamente pelo ajax.php antes de
 * chamar a API de qualquer módulo. Dentro da api/ de um módulo, a
 * variável $pdo já estará disponível.
 */

// --- Dados fornecidos pelo painel do InfinityFree ---
$dbHost = 'sql208.infinityfree.com';
$dbNome = 'if0_41376977_bdd_pnsg';
$dbUser = 'if0_41376977';
$dbSenha = 'Q7a4W8s5E9d6';
$dbCharset = 'utf8mb4';

$dsn = "mysql:host={$dbHost};dbname={$dbNome};charset={$dbCharset}";

$opcoes = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbSenha, $opcoes);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    // Não exponha $e->getMessage() em produção (pode revelar dados sensíveis)
    echo json_encode(['erro' => 'Falha na conexão com o banco de dados.']);
    exit;
}
