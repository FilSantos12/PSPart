<?php
/**
 * Bloco "Disponibilidade" do formulário de produto — incluído por produto-novo.php e
 * produto-editar.php (mesmo markup e JS nos dois). Espera $dados com modo_venda,
 * permite_venda_sem_estoque, prazo_encomenda_dias e estoque. O campo Estoque continua
 * no formulário original; aqui ele só é lido para o preview.
 *
 * O preview chama ajax-disponibilidade.php (mesmo helper da loja) — o JS nunca compõe label.
 */
require_once __DIR__ . '/../helpers/disponibilidade.php';

$dispModo    = $dados['modo_venda'] ?? 'automatico';
$dispPermite = !empty($dados['permite_venda_sem_estoque']);
$dispPrazo   = $dados['prazo_encomenda_dias'] ?? '';
$dispInicial = disponibilidade_produto([
    'estoque'                   => is_numeric($dados['estoque'] ?? '') ? (int) $dados['estoque'] : 0,
    'modo_venda'                => $dispModo,
    'permite_venda_sem_estoque' => $dispPermite ? 1 : 0,
    'prazo_encomenda_dias'      => $dispPrazo,
]);
$dispMostraPermite = $dispModo === 'automatico';
$dispMostraPrazo   = $dispModo === 'sempre_sob_encomenda' || ($dispModo === 'automatico' && $dispPermite);
?>
<div class="col-12" id="disp-bloco">
    <label class="form-label fw-semibold">
        <i class="fas fa-boxes-stacked me-1 text-muted"></i> Disponibilidade
    </label>
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label form-label-sm text-muted" for="disp-modo">Modo de venda</label>
            <select name="modo_venda" id="disp-modo" class="form-select form-select-sm">
                <?php foreach (MODOS_VENDA as $valor => $rotulo): ?>
                    <option value="<?= htmlspecialchars($valor) ?>" <?= $dispModo === $valor ? 'selected' : '' ?>><?= htmlspecialchars($rotulo) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4" id="disp-permite-wrap" style="<?= $dispMostraPermite ? '' : 'display:none;' ?>">
            <div class="form-check mb-1">
                <input type="checkbox" name="permite_venda_sem_estoque" value="1" id="disp-permite" class="form-check-input" <?= $dispPermite ? 'checked' : '' ?>>
                <label class="form-check-label small" for="disp-permite">Permitir venda sem estoque</label>
            </div>
        </div>
        <div class="col-md-4" id="disp-prazo-wrap" style="<?= $dispMostraPrazo ? '' : 'display:none;' ?>">
            <label class="form-label form-label-sm text-muted" for="disp-prazo">Prazo sob encomenda (dias úteis)</label>
            <input type="number" name="prazo_encomenda_dias" id="disp-prazo" class="form-control form-control-sm"
                   min="1" max="365" placeholder="vazio = consulte prazo" value="<?= htmlspecialchars((string) $dispPrazo) ?>">
        </div>
    </div>
    <div class="mt-2 small text-muted">
        Disponibilidade calculada:
        <span id="disp-preview" class="stock-badge <?= htmlspecialchars($dispInicial['badge_class']) ?> ms-1 mb-0"><span class="stock-dot"></span><span id="disp-preview-label"><?= htmlspecialchars($dispInicial['label_admin']) ?></span></span>
    </div>
</div>
<script>
(function () {
    const bloco = document.getElementById('disp-bloco');
    const form  = bloco?.closest('form');
    if (!form) return;

    const modoEl    = document.getElementById('disp-modo');
    const permiteEl = document.getElementById('disp-permite');
    const prazoEl   = document.getElementById('disp-prazo');
    const estoqueEl = form.querySelector('[name="estoque"]');
    const previewEl = document.getElementById('disp-preview');
    const labelEl   = document.getElementById('disp-preview-label');
    const classes   = ['stock-badge--ok', 'stock-badge--warn', 'stock-badge--req'];

    // Só decide QUAIS campos aparecem — o rótulo do preview vem sempre do servidor
    function atualizarCampos() {
        const auto = modoEl.value === 'automatico';
        document.getElementById('disp-permite-wrap').style.display = auto ? '' : 'none';
        document.getElementById('disp-prazo-wrap').style.display =
            (modoEl.value === 'sempre_sob_encomenda' || (auto && permiteEl.checked)) ? '' : 'none';
    }

    function mostrarFalha() {
        previewEl.classList.remove(...classes);
        labelEl.textContent = '—';
    }

    let timer = null;
    let seq   = 0;
    function agendarPreview() {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            const minha = ++seq;
            try {
                const resp = await fetch('ajax-disponibilidade.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        estoque:                   estoqueEl ? estoqueEl.value : '',
                        modo_venda:                modoEl.value,
                        permite_venda_sem_estoque: permiteEl.checked ? 1 : 0,
                        prazo_encomenda_dias:      prazoEl.value,
                    }),
                });
                const json = await resp.json();
                if (minha !== seq) return;              // resposta atrasada de uma digitação anterior
                if (!resp.ok || !json.ok) return mostrarFalha();
                previewEl.classList.remove(...classes);
                previewEl.classList.add(json.data.badge_class);
                labelEl.textContent = json.data.label_admin;
            } catch (_) {
                if (minha === seq) mostrarFalha();
            }
        }, 300);
    }

    [modoEl, permiteEl, prazoEl, estoqueEl].forEach(el => {
        el?.addEventListener('input', () => { atualizarCampos(); agendarPreview(); });
        el?.addEventListener('change', () => { atualizarCampos(); agendarPreview(); });
    });
})();
</script>
