<?php
// Modal de confirmação compartilhado (Patch U) — mesmo padrão do #modalExcluir de produtos.php:
// um modal só, com título, texto e botão trocados por ação. Chamar admin_modal_confirmar()
// antes do layout_foot(); o JS roda no clique, quando o bundle do Bootstrap já carregou.
//
// Dois jeitos de usar:
//   - formulário: <form data-confirmar-titulo="…" data-confirmar-texto="…" data-confirmar-botao="…">
//     o envio espera o modal; Cancelar não envia. O texto é HTML — escapar o nome do item antes
//     e o atributo inteiro de novo (htmlspecialchars duas vezes, uma por camada).
//   - JS: admConfirmar({ titulo, texto, botao, classe }) → Promise<boolean>.
// O texto descreve o que o servidor vai fazer de verdade (regra de UI honesta do Patch P).

function admin_modal_confirmar(): void {
?>
<div class="modal fade" id="admConfirmar" tabindex="-1" aria-labelledby="admConfirmarTitulo" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="admConfirmarTitulo"></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body" id="admConfirmarTexto"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm" id="admConfirmarBotao"></button>
            </div>
        </div>
    </div>
</div>
<script>
function admConfirmar({ titulo, texto, botao, classe = 'btn-danger' }) {
    const el = document.getElementById('admConfirmar');
    document.getElementById('admConfirmarTitulo').textContent = titulo;
    document.getElementById('admConfirmarTexto').innerHTML    = texto;
    const btn = document.getElementById('admConfirmarBotao');
    btn.textContent = botao;
    btn.className   = 'btn btn-sm ' + classe;
    const modal = bootstrap.Modal.getOrCreateInstance(el);
    return new Promise(resolve => {
        let confirmado = false;
        btn.onclick = () => { confirmado = true; modal.hide(); };
        // Resolve só depois de fechar: Esc, X, fundo e Cancelar contam como "não"
        el.addEventListener('hidden.bs.modal', () => { btn.onclick = null; resolve(confirmado); }, { once: true });
        modal.show();
    });
}

document.addEventListener('submit', (e) => {
    const form = e.target.closest('form[data-confirmar-titulo]');
    if (!form) return;
    e.preventDefault();
    admConfirmar({
        titulo: form.dataset.confirmarTitulo,
        texto:  form.dataset.confirmarTexto,
        botao:  form.dataset.confirmarBotao,
    }).then(ok => { if (ok) form.submit(); });   // form.submit() não dispara 'submit' de novo
});
</script>
<?php
}
