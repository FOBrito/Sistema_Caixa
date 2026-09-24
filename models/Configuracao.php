<?php
/**
 * models/Configuracao.php
 *
 * MODEL compartilhado — pequena tabela chave/valor para configurações
 * gerais do evento (hoje só usada para "venda liberada/bloqueada",
 * mas serve para qualquer outro liga/desliga futuro).
 */

class Configuracao
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function obter(string $chave, ?string $padrao = null): ?string
    {
        $stmt = $this->pdo->prepare('SELECT valor FROM configuracoes WHERE chave = :chave');
        $stmt->bindParam(':chave', $chave);
        $stmt->execute();

        $valor = $stmt->fetchColumn();

        return $valor !== false ? $valor : $padrao;
    }

    public function definir(string $chave, string $valor): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO configuracoes (chave, valor) VALUES (:chave, :valor)
             ON DUPLICATE KEY UPDATE valor = :valor2'
        );
        $stmt->bindValue(':chave', $chave);
        $stmt->bindValue(':valor', $valor);
        $stmt->bindValue(':valor2', $valor);
        $stmt->execute();
    }

    /**
     * Se ainda não houver registro na tabela, a venda começa LIBERADA
     * por padrão (evita bloquear o evento sem querer no primeiro uso).
     */
    public function vendaLiberada(): bool
    {
        return $this->obter('venda_liberada', '1') === '1';
    }

    public function definirVendaLiberada(bool $liberada): void
    {
        $this->definir('venda_liberada', $liberada ? '1' : '0');
    }
}
