<?php
/**
 * models/Cartao.php
 *
 * MODEL compartilhado (usado por caixa e barracas) responsável pela
 * tabela `saldo_card`.
 * Cada linha da tabela é um LANÇAMENTO (cadastro inicial ou recarga).
 * O saldo total do cartão é a soma de todos os lançamentos dele.
 */

class Cartao
{
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Verifica se o cartão já possui algum lançamento (ou seja, já foi cadastrado)
     */
    public function existeCartao(int $idCard): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM saldo_card WHERE id_card = :id_card');
        $stmt->bindParam(':id_card', $idCard, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }

    /** Formas de pagamento aceitas no caixa (cadastro/recarga) */
    public const FORMAS_PAGAMENTO_VALIDAS = ['pix', 'dinheiro', 'debito', 'credito'];

    /**
     * Registra um novo lançamento de saldo.
     *
     * @param string $tipo 'cadastro', 'recarga' ou 'consumo' (consumo deve
     *                     vir com $saldo NEGATIVO — é sempre quem chama,
     *                     e não este método, quem decide o sinal do valor)
     * @param string|null $formaPagamento Obrigatório para 'cadastro'/'recarga'
     *                     (pix/dinheiro/debito/credito). Ignorado (forçado a
     *                     null) para 'consumo', que não é uma entrada de
     *                     dinheiro no caixa.
     * @return int O id do lançamento gravado (útil pra rastreabilidade/logs)
     *
     * @throws InvalidArgumentException dados de entrada inválidos
     * @throws RuntimeException não foi possível confirmar a gravação —
     *         isso é FINANCEIRO, então aqui NUNCA se assume sucesso: o
     *         método sempre relê o que acabou de gravar antes de retornar.
     */
    public function registrarTransacao(
        int $idCard,
        float $saldo,
        string $tipo = 'recarga',
        ?string $formaPagamento = null
    ): int {
        $tiposValidos = ['cadastro', 'recarga', 'consumo'];
        if (!in_array($tipo, $tiposValidos, true)) {
            throw new InvalidArgumentException('Tipo de lançamento inválido.');
        }

        if (in_array($tipo, ['cadastro', 'recarga'], true)) {
            if (!in_array($formaPagamento, self::FORMAS_PAGAMENTO_VALIDAS, true)) {
                throw new InvalidArgumentException('Forma de pagamento inválida.');
            }
        } else {
            // 'consumo' (venda na barraca) não é dinheiro entrando no caixa
            $formaPagamento = null;
        }

        date_default_timezone_set('America/Sao_Paulo');
        $datTrans = date('Y-m-d H:i:s');

        $sql = "INSERT INTO saldo_card (id_card, tipo, forma_pagamento, dat_trans, saldo)
                VALUES (:id_card, :tipo, :forma_pagamento, :dat_trans, :saldo)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id_card', $idCard, PDO::PARAM_INT);
        $stmt->bindParam(':tipo', $tipo);
        $stmt->bindParam(':forma_pagamento', $formaPagamento);
        $stmt->bindParam(':dat_trans', $datTrans);
        $stmt->bindParam(':saldo', $saldo);

        $executado = $stmt->execute();

        // 1ª checagem: mesmo com PDO::ERRMODE_EXCEPTION ligado (que já
        // deveria lançar exceção sozinho em caso de falha), NUNCA
        // confiamos apenas em "não deu erro" — conferimos o retorno
        // explícito do execute() e quantas linhas foram afetadas.
        if ($executado !== true || $stmt->rowCount() !== 1) {
            throw new RuntimeException('Falha ao gravar o lançamento no banco de dados.');
        }

        $idInserido = (int) $this->pdo->lastInsertId();

        // 2ª checagem (a mais importante): relemos o registro que
        // acabamos de gravar e comparamos com o que pedimos para
        // gravar. Só depois disso o método considera "sucesso" de
        // verdade — é assim que evitamos reportar sucesso sem o dado
        // estar realmente no banco.
        $confere = $this->pdo->prepare(
            'SELECT id_card, tipo, saldo FROM saldo_card WHERE id = :id'
        );
        $confere->bindParam(':id', $idInserido, PDO::PARAM_INT);
        $confere->execute();
        $linha = $confere->fetch();

        $confirmado = $linha
            && (int) $linha['id_card'] === $idCard
            && $linha['tipo'] === $tipo
            && abs((float) $linha['saldo'] - $saldo) < 0.001;

        if (!$confirmado) {
            throw new RuntimeException('Não foi possível confirmar a gravação do lançamento no banco de dados.');
        }

        return $idInserido;
    }

    /**
     * Retorna o saldo atual do cartão (soma de todos os lançamentos).
     * Útil para mostrar o saldo depois de uma recarga, ou no módulo
     * "barracas" quando for debitar um consumo.
     */
    public function saldoAtual(int $idCard): float
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(saldo), 0) FROM saldo_card WHERE id_card = :id_card');
        $stmt->bindParam(':id_card', $idCard, PDO::PARAM_INT);
        $stmt->execute();

        return (float) $stmt->fetchColumn();
    }

    /**
     * Extrai o id_card a partir do texto BRUTO lido do QR Code.
     *
     * Aceita 2 formatos:
     *  - Número puro: "123"
     *  - Link com o número num parâmetro: "https://site.com/?i=123"
     *    (aceita os nomes de parâmetro i, id, id_card ou card)
     *
     * Propositalmente NÃO tenta "adivinhar" nada além disso (ex: não
     * sai extraindo qualquer dígito solto de dentro do texto) — isso
     * é dado financeiro, prefiro falhar com clareza a arriscar
     * interpretar um cartão errado.
     *
     * @return int|null null se não conseguir reconhecer nenhum dos formatos
     */
    public static function extrairIdDoTextoQr(string $texto): ?int
    {
        $texto = trim($texto);

        if ($texto === '') {
            return null;
        }

        // Formato 1: número puro
        if (ctype_digit($texto)) {
            return (int) $texto;
        }

        // Formato 2: link com o número num parâmetro da query string
        $partes = parse_url($texto);
        if (!empty($partes['query'])) {
            parse_str($partes['query'], $params);
            foreach (['i', 'id', 'id_card', 'card'] as $chave) {
                if (isset($params[$chave]) && ctype_digit((string) $params[$chave])) {
                    return (int) $params[$chave];
                }
            }
        }

        return null;
    }
}
