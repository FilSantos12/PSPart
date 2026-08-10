/**
 * assets/js/agente.js
 * Widget do agente conversacional de compras — PSPart v1
 * Padrão classe App (ES6), consistente com script.js
 */

class AgenteChat {
    constructor() {
        this._historico = []; // mensagens em memória: [{role, content}]
        this._pensando  = false;

        this._btn    = document.getElementById('agente-btn');
        this._painel = document.getElementById('agente-painel');
        this._msgs   = document.getElementById('agente-msgs');
        this._input  = document.getElementById('agente-input');
        this._enviar = document.getElementById('agente-enviar');
        this._fechar = document.getElementById('agente-header-close');

        if (!this._btn || !this._painel) return;

        this._bind();
        this._agendarAbertura();
    }

    _bind() {
        // Abrir/fechar painel
        this._btn.addEventListener('click', () => this._toggle());
        this._fechar.addEventListener('click', () => this._fechar_painel());

        // Enviar
        this._enviar.addEventListener('click', () => this._enviarMensagem());
        this._input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this._enviarMensagem();
            }
        });
    }

    _agendarAbertura() {
        // Abre automaticamente após 10s, apenas uma vez por sessão
        if (sessionStorage.getItem('psp_agente_auto_aberto')) return;
        setTimeout(() => {
            if (this._painel.style.display !== 'flex') {
                this._abrir_painel();
                sessionStorage.setItem('psp_agente_auto_aberto', '1');
            }
        }, 10000);
    }

    _toggle() {
        const visivel = this._painel.style.display === 'flex';
        if (visivel) {
            this._fechar_painel();
        } else {
            this._abrir_painel();
        }
    }

    _abrir_painel() {
        this._painel.style.display = 'flex';
        // Saudação inicial apenas na primeira abertura
        if (this._msgs.children.length === 0) {
            this._addMsg('bot', 'Olá! Sou o assistente da PSPart. Posso ajudar com produtos, frete e status de pedido. 😊');
        }
        this._input.focus();
    }

    _fechar_painel() {
        this._painel.style.display = 'none';
    }

    async _enviarMensagem() {
        const texto = this._input.value.trim();
        if (!texto || this._pensando) return;

        this._input.value = '';
        this._addMsg('user', texto);
        this._setPensando(true);

        try {
            const resp = await fetch('backend/api/agente.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ mensagem: texto, historico: this._historico }),
            });

            if (!resp.ok) throw new Error('HTTP ' + resp.status);

            const data = await resp.json();

            if (data.erro) {
                this._addMsg('aviso', 'Erro: ' + data.erro);
                return;
            }

            const respostaTexto = data.resposta || 'Sem resposta.';
            this._addMsg('bot', respostaTexto);

            // Atualiza histórico em memória
            this._historico.push({ role: 'user',      content: texto          });
            this._historico.push({ role: 'assistant', content: respostaTexto  });

            // Limita histórico para não crescer demais
            if (this._historico.length > 40) {
                this._historico = this._historico.slice(-40);
            }

        } catch (err) {
            this._addMsg('aviso', 'Assistente indisponível no momento. Tente novamente.');
        } finally {
            this._setPensando(false);
        }
    }

    _addMsg(tipo, texto) {
        // Remove indicador "digitando" se existir
        const typing = this._msgs.querySelector('.agente-typing');
        if (typing) typing.remove();

        const div = document.createElement('div');
        div.className = 'agente-msg agente-msg--' + tipo;
        div.textContent = texto;
        this._msgs.appendChild(div);
        this._msgs.scrollTop = this._msgs.scrollHeight;
    }

    _setPensando(val) {
        this._pensando        = val;
        this._enviar.disabled = val;
        this._input.disabled  = val;

        if (val) {
            const typing = document.createElement('div');
            typing.className   = 'agente-typing';
            typing.textContent = 'Assistente digitando…';
            this._msgs.appendChild(typing);
            this._msgs.scrollTop = this._msgs.scrollHeight;
        }
    }
}

// Inicializa após o DOM estar pronto
document.addEventListener('DOMContentLoaded', () => {
    // Injeta o HTML do widget no body
    const html = `
<button id="agente-btn" title="Falar com o assistente" aria-label="Assistente de compras">
    <i class="fas fa-comment-dots"></i>
</button>
<div id="agente-painel" style="display:none;">
    <div id="agente-header">
        <span><i class="fas fa-robot"></i> Assistente PSPart</span>
        <button id="agente-header-close" aria-label="Fechar chat">&times;</button>
    </div>
    <div id="agente-msgs" role="log" aria-live="polite"></div>
    <div id="agente-footer">
        <textarea id="agente-input" rows="1" placeholder="Digite sua dúvida…" aria-label="Mensagem para o assistente"></textarea>
        <button id="agente-enviar" aria-label="Enviar mensagem"><i class="fas fa-paper-plane"></i></button>
    </div>
</div>`;

    document.body.insertAdjacentHTML('beforeend', html);
    new AgenteChat();
});
