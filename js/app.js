/**
 * js/app.js
 * NÚCLEO GLOBAL DA SPA (router via AJAX)
 *
 * Responsável por:
 *  - Trocar de módulo (home, caixa, barracas...) sem recarregar a página
 *  - Trocar o CSS/JS carregado de acordo com o módulo ativo
 *  - Nunca mudar a URL do navegador (fica sempre em app1.com.br)
 *
 * Qualquer botão/link que precise navegar entre módulos deve ter o
 * atributo data-modulo="nome_do_modulo", exemplo:
 *   <button data-modulo="caixa">Caixa</button>
 */

const App = {

    elApp: null,
    moduloAtual: 'home',
    intervalos: [], // setInterval's criados por módulos (ex: monitoramento do barracas)

    init() {
        this.elApp = document.getElementById('app');
        this.bindNavegacao();
    },

    // Usado por módulos que precisam de setInterval (ex: barracas
    // monitorando se as vendas continuam liberadas). Registrando aqui,
    // o próprio router limpa automaticamente ao trocar de módulo —
    // sem isso, o intervalo continuaria rodando escondido em segundo
    // plano mesmo depois da pessoa sair da tela.
    registrarIntervalo(id) {
        this.intervalos.push(id);
    },

    limparIntervalos() {
        this.intervalos.forEach((id) => clearInterval(id));
        this.intervalos = [];
    },

    // Delegação de evento: funciona até para elementos criados depois
    // (ex: botões que vêm dentro do HTML carregado via AJAX)
    bindNavegacao() {
        document.addEventListener('click', (evento) => {
            const el = evento.target.closest('[data-modulo]');
            if (!el) return;

            evento.preventDefault();
            const modulo = el.getAttribute('data-modulo');

            // Qualquer outro data-* no elemento vira parâmetro extra na URL.
            // Ex: <button data-modulo="barracas" data-barraca="caldos">
            //     -> ajax.php?modulo=barracas&acao=view&barraca=caldos
            const params = {};
            for (const chave in el.dataset) {
                if (chave === 'modulo') continue;
                params[chave] = el.dataset[chave];
            }

            this.carregarModulo(modulo, params);
        });
    },

    carregarModulo(modulo, params = {}) {
        if (!modulo) return;

        this.limparIntervalos(); // encerra qualquer monitoramento do módulo anterior

        this.elApp.innerHTML = '<p class="mensagem">Carregando...</p>';

        const query = new URLSearchParams({ modulo, acao: 'view', ...params }).toString();

        fetch(`ajax.php?${query}`)
            .then((resposta) => {
                if (!resposta.ok) throw new Error('Falha ao carregar módulo: ' + modulo);
                return resposta.text();
            })
            .then((html) => {
                this.elApp.innerHTML = html;
                this.moduloAtual = modulo;
                this.trocarAssetsDoModulo(modulo);
            })
            .catch((erro) => {
                this.elApp.innerHTML = '<p class="mensagem">Não foi possível carregar esta tela.</p>';
                console.error(erro);
            });
    },

    // Troca o <link> de CSS e recarrega o <script> do módulo.
    // (um <script> já executado não roda de novo só por trocar o innerHTML,
    // por isso removemos e recriamos a tag)
    trocarAssetsDoModulo(modulo) {
        const versao = Date.now();

        const linkCss = document.getElementById('modulo-css');
        if (linkCss) {
            linkCss.href = `modules/${modulo}/css/style.css?v=${versao}`;
        }

        const scriptAntigo = document.getElementById('modulo-js');
        if (scriptAntigo) {
            scriptAntigo.remove();
        }

        const script = document.createElement('script');
        // ?v=timestamp evita que o navegador (ou o servidor) sirva uma
        // versão em cache do JS do módulo depois de uma atualização —
        // sem isso, o HTML novo aparece mas o comportamento continua
        // sendo o do JS antigo, como se nada tivesse mudado.
        script.src = `modules/${modulo}/js/app.js?v=${versao}`;
        script.id = 'modulo-js';
        document.body.appendChild(script);
    }
};

document.addEventListener('DOMContentLoaded', () => App.init());
