/**
 * ATMParts - Script principal
 * Gerencia interações da interface, modais e formulários
 */

// Número central do WhatsApp da loja — mesmo placeholder usado nos links estáticos do index.html
const WHATSAPP_NUMBER = 'SEU_NUMERO';

class App {
    constructor() {
        this._activeCategory = 'all';
        this._searchQuery    = '';
        this._onlyInStock    = false;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupScrollHandlers();
        this.setupCounters();
        this.setupLGPD();
        this.setupThemeToggle();
        this.setupCheckout();
        this.setupImageLightbox();
        this.setupProductFilter();
        this.setupProductToolbar();
        this.setupProductSharing();
        this.renderProducts();
        this.handleRetornoMP();
        this.setupFrete();
    }

    setupEventListeners() {
        this.setupContactForm();
        this.setupModalButtons();
        this.setupNavigation();
    }

    setupContactForm() {
        const contactForm = document.getElementById('contactForm');
        if (!contactForm) return;

        contactForm.addEventListener('submit', (e) => {
            e.preventDefault(); // Impede o envio padrão e redirecionamento
            
            if (this.validateForm()) {
                this.showLoadingState(true);
                
                // Cria um formulário temporário para submissão
                this.submitForm(contactForm)
                    .then(() => {
                        // Sucesso: mostra o modal de confirmação
                        this.showConfirmationModal();
                        contactForm.reset();
                    })
                    .catch((error) => {
                        console.error('Erro no envio:', error);
                        this.showErrorModal('Erro no envio. Tente novamente ou entre em contato diretamente.');
                    })
                    .finally(() => {
                        this.showLoadingState(false);
                    });
            } else {
                this.showErrorModal('Por favor, preencha todos os campos obrigatórios.');
            }
        });
    }

    submitForm(form) {
        return fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'Accept': 'application/json' }
        }).then(response => {
            if (!response.ok) throw new Error('Falha no envio do formulário');
        });
    }

    showLoadingState(show) {
        const submitButton = document.querySelector('#contactForm button[type="submit"]');
        
        if (submitButton) {
            if (show) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Enviando...';
            } else {
                submitButton.disabled = false;
                submitButton.innerHTML = 'Enviar Mensagem';
            }
        }
    }

    showConfirmationModal() {
        // Mostra o modal de confirmação
        const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
        confirmationModal.show();
    }

    showErrorModal(message) {
        // Atualiza a mensagem de erro se fornecida
        if (message) {
            const errorText = document.querySelector('#errorModal .modal-body p');
            if (errorText) {
                errorText.textContent = message;
            }
        }
        
        // Mostra o modal de erro
        const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
        errorModal.show();
    }

    validateForm() {
        const requiredFields = [
            document.getElementById('nome'),
            document.getElementById('email'), 
            document.getElementById('assunto'),
            document.getElementById('mensagem')
        ];
        
        return requiredFields.every(field => field && field.value.trim() !== '');
    }

    setupModalButtons() {
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('solicitar-orcamento')) {
                this.handleQuoteRequest(e.target);
            }
        });
    }

    handleQuoteRequest(button) {
        const modalElement = button.closest('.modal');
        const productName = button.dataset.product || this.extractProductName(modalElement);
        
        this.closeModal(modalElement);
        this.fillContactForm(productName);
        this.scrollToContactForm();
    }

    extractProductName(modalElement) {
        const titleElement = modalElement?.querySelector('.modal-title');
        return titleElement ? titleElement.textContent.replace(' - Detalhes', '') : 'Produto';
    }

    closeModal(modalElement) {
        if (!modalElement) return;
        
        const modal = bootstrap.Modal.getInstance(modalElement);
        modal?.hide();
    }

    fillContactForm(productName) {
        const assuntoField = document.getElementById('assunto');
        if (assuntoField) {
            assuntoField.value = `Orçamento: ${productName}`;
        }
    }

    scrollToContactForm() {
        setTimeout(() => {
            const contactSection = document.getElementById('contato');
            if (contactSection) {
                contactSection.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start' 
                });
            }
        }, 300);
    }

    setupNavigation() {
        const verProdutosBtn = document.querySelector('.hero-section .btn-primary');
        if (verProdutosBtn) {
            verProdutosBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.scrollToSection('produtos');
            });
        }
    }

    scrollToSection(sectionId) {
        const section = document.getElementById(sectionId);
        if (section) {
            section.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'start' 
            });
        }
    }

    setupScrollHandlers() {
        const sections = document.querySelectorAll('section');
        const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
        const backToTopBtn = document.getElementById('backToTop');

        if (!sections.length && !backToTopBtn) return;

        let ticking = false;
        window.addEventListener('scroll', () => {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(() => {
                if (sections.length && navLinks.length) {
                    this.updateActiveNavLink(sections, navLinks);
                }
                if (backToTopBtn) {
                    backToTopBtn.classList.toggle('visible', window.pageYOffset > 300);
                }
                ticking = false;
            });
        });

        backToTopBtn?.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    updateActiveNavLink(sections, navLinks) {
        let currentSection = '';
        const scrollPosition = window.pageYOffset;

        sections.forEach(section => {
            if (scrollPosition >= (section.offsetTop - 100)) {
                currentSection = section.getAttribute('id');
            }
        });

        navLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href').substring(1) === currentSection) {
                link.classList.add('active');
            }
        });
    }

    setupThemeToggle() {
        const btn = document.getElementById('themeToggle');
        if (!btn) return;

        const icon = btn.querySelector('i');

        // Sincroniza o ícone com o estado atual (definido pelo anti-FOUC no <body>)
        const updateIcon = (isDark) => {
            icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
        };

        updateIcon(document.body.classList.contains('dark-mode'));

        btn.addEventListener('click', () => {
            const isDark = document.body.classList.toggle('dark-mode');
            updateIcon(isDark);
            localStorage.setItem('psp_theme', isDark ? 'dark' : 'light');
        });
    }

    setupProductFilter() {
        const filtersEl = document.getElementById('product-filters');
        if (!filtersEl) return;

        // Event delegation: um único listener no container, sempre lê .product-col do DOM atual
        filtersEl.addEventListener('click', (e) => {
            const btn = e.target.closest('.filter-btn');
            if (!btn) return;

            filtersEl.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            this._activeCategory = btn.dataset.filter;
            this._applyProductVisibility();
        });
    }

    // ── BUSCA / ORDENAÇÃO / FILTRO DE DISPONIBILIDADE ─────────────────────────

    setupProductToolbar() {
        const searchEl = document.getElementById('product-search');
        searchEl?.addEventListener('input', () => {
            this._searchQuery = searchEl.value.trim().toLowerCase();
            this._applyProductVisibility();
        });

        const stockEl = document.getElementById('product-instock');
        stockEl?.addEventListener('change', () => {
            this._onlyInStock = stockEl.checked;
            this._applyProductVisibility();
        });

        const sortEl = document.getElementById('product-sort');
        sortEl?.addEventListener('change', () => {
            this._applyProductSort(sortEl.value);
        });
    }

    // Critério único de visibilidade: categoria + busca + disponibilidade (AND lógico)
    _isProductVisible(col) {
        const matchesCategory = this._activeCategory === 'all' || col.dataset.category === this._activeCategory;
        const matchesStock    = !this._onlyInStock || parseInt(col.dataset.estoque) > 0;

        const query = this._searchQuery;
        const matchesSearch = !query
            || (col.dataset.nome   || '').toLowerCase().includes(query)
            || (col.dataset.codigo || '').toLowerCase().includes(query);

        return matchesCategory && matchesStock && matchesSearch;
    }

    _applyProductVisibility() {
        const grid = document.getElementById('products-grid');
        if (!grid) return;

        const cols = grid.querySelectorAll('.product-col');
        let anyVisible = false;

        cols.forEach(col => {
            const visible = this._isProductVisible(col);
            // Visibilidade via style.display inline — nunca via regra de stylesheet (padrão do projeto)
            col.style.display = visible ? '' : 'none';
            if (visible) anyVisible = true;
        });

        const emptyMsg = document.getElementById('products-empty-msg');
        if (emptyMsg) emptyMsg.style.display = (cols.length && !anyVisible) ? '' : 'none';
    }

    // Reordena os .product-col já existentes no DOM (appendChild) — não re-renderiza
    _applyProductSort(sortValue) {
        const grid = document.getElementById('products-grid');
        if (!grid || !sortValue) return;

        const comparators = {
            'preco-asc':        (a, b) => parseFloat(a.dataset.preco) - parseFloat(b.dataset.preco),
            'preco-desc':       (a, b) => parseFloat(b.dataset.preco) - parseFloat(a.dataset.preco),
            'nome-asc':         (a, b) => (a.dataset.nome || '').localeCompare(b.dataset.nome || '', 'pt-BR'),
            'disponibilidade':  (a, b) => (parseInt(b.dataset.estoque) > 0) - (parseInt(a.dataset.estoque) > 0),
        };
        const cmp = comparators[sortValue];
        if (!cmp) return;

        Array.from(grid.querySelectorAll('.product-col'))
            .sort(cmp)
            .forEach(col => grid.appendChild(col));
    }

    // Reaplica busca/ordenação/disponibilidade depois de qualquer (re)render do grid
    _applyCurrentToolbarState() {
        this._applyProductVisibility();
        const sortEl = document.getElementById('product-sort');
        if (sortEl?.value) this._applyProductSort(sortEl.value);
    }

    // ── DEEP LINK DE PRODUTO (?produto=ID) ───────────────────────────────────

    _openDeepLinkProduct() {
        const id = new URLSearchParams(window.location.search).get('produto');
        if (!id) return;

        const modalEl = document.getElementById('productModal' + id);
        if (!modalEl) {
            console.warn(`Deep link: produto "${id}" não encontrado ou indisponível.`);
            return;
        }
        new bootstrap.Modal(modalEl).show();
    }

    // ── COMPARTILHAMENTO DE PRODUTO ───────────────────────────────────────────

    setupProductSharing() {
        document.addEventListener('click', (e) => {
            const waBtn = e.target.closest('.btn-whatsapp-share');
            if (waBtn) {
                e.preventDefault();
                this._shareViaWhatsApp(waBtn.dataset);
                return;
            }

            const shareBtn = e.target.closest('.btn-share-product');
            if (shareBtn) {
                e.preventDefault();
                this._shareProduct(shareBtn);
                return;
            }

            const copyCodeBtn = e.target.closest('.btn-copy-code');
            if (copyCodeBtn) {
                e.preventDefault();
                this._copyToClipboard(copyCodeBtn.dataset.codigo, copyCodeBtn, 'Copiado!');
            }
        });
    }

    _getProductShareUrl(productId) {
        return `${window.location.origin}${window.location.pathname}?produto=${productId}`;
    }

    _shareViaWhatsApp(data) {
        const { productId, productName, productCode } = data;
        const url = this._getProductShareUrl(productId);

        let mensagem = `Tenho interesse no ${productName}`;
        if (productCode) mensagem += ` (código ${productCode})`;
        mensagem += `: ${url}`;

        window.open(`https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(mensagem)}`, '_blank', 'noopener');
    }

    async _shareProduct(btnEl) {
        const { productId, productName, productCode } = btnEl.dataset;
        const url  = this._getProductShareUrl(productId);
        const text = productCode ? `${productName} (código ${productCode})` : productName;

        if (navigator.share) {
            try {
                await navigator.share({ title: productName, text, url });
                return;
            } catch (err) {
                if (err.name === 'AbortError') return; // usuário cancelou o compartilhamento nativo
                // qualquer outro erro cai para o fallback de clipboard abaixo
            }
        }
        await this._copyToClipboard(url, btnEl, 'Link copiado!');
    }

    async _copyToClipboard(value, btnEl, feedbackText = 'Copiado!') {
        if (!value) return;

        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(value);
            } else {
                // Fallback para contexto não-seguro (HTTP) ou navegadores sem Clipboard API
                const ta = document.createElement('textarea');
                ta.value = value;
                ta.style.position = 'fixed';
                ta.style.opacity   = '0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            }
            this._showCopyFeedback(btnEl, feedbackText);
        } catch (_) {
            // Clipboard indisponível — degrada silenciosamente, sem exceção não tratada
        }
    }

    // Feedback só de ícone (nunca texto) — funciona tanto nos botões circulares de ação
    // quanto no botão de copiar código, sem estourar layout fixo de nenhum dos dois
    _showCopyFeedback(btnEl, title) {
        if (!btnEl) return;
        const original      = btnEl.innerHTML;
        const originalTitle = btnEl.title;
        btnEl.innerHTML = '<i class="fas fa-check"></i>';
        btnEl.title     = title;
        btnEl.disabled  = true;
        setTimeout(() => {
            btnEl.innerHTML = original;
            btnEl.title     = originalTitle;
            btnEl.disabled  = false;
        }, 1800);
    }

    setupLGPD() {
        const banner = document.getElementById('lgpdBanner');
        if (!banner) return;

        const consent = localStorage.getItem('psp_lgpd_consent');
        if (consent === 'accepted') {
            gtag('consent', 'update', { 'analytics_storage': 'granted' });
            return;
        }
        if (consent === 'rejected') return;

        banner.style.display = '';

        document.getElementById('lgpdAccept')?.addEventListener('click', () => {
            localStorage.setItem('psp_lgpd_consent', 'accepted');
            gtag('consent', 'update', { 'analytics_storage': 'granted' });
            banner.style.display = 'none';
        });

        document.getElementById('lgpdReject')?.addEventListener('click', () => {
            localStorage.setItem('psp_lgpd_consent', 'rejected');
            banner.style.display = 'none';
        });
    }

    setupCounters() {
        const counters = document.querySelectorAll('.stat-number');
        if (counters.length === 0) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !entry.target.dataset.animated) {
                    entry.target.dataset.animated = 'true';
                    this.animateCounter(entry.target, parseInt(entry.target.dataset.target));
                }
            });
        }, { threshold: 0.5 });

        counters.forEach(counter => observer.observe(counter));
    }

    animateCounter(el, target) {
        const duration = 1500;
        const start = performance.now();

        const update = (time) => {
            const elapsed = time - start;
            const progress = Math.min(elapsed / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.floor(eased * target);
            if (progress < 1) requestAnimationFrame(update);
        };

        requestAnimationFrame(update);
    }

    // ── CHECKOUT ──────────────────────────────────────────────────────────────

    setupCheckout() {
        this._checkoutPrice      = 0;
        this._checkoutProductId  = 0;
        this._brickErrorHandled  = false;
        this._selectedFrete      = null;

        // Abre o modal de checkout ao clicar em qualquer .btn-buy
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-buy');
            if (!btn) return;

            const productName  = btn.dataset.productName;
            const productPrice = parseFloat(btn.dataset.productPrice);

            // Fecha modal de produto se estiver aberto
            const parentModal = btn.closest('.modal');
            if (parentModal) {
                bootstrap.Modal.getInstance(parentModal)?.hide();
            }

            this._checkoutPrice     = productPrice;
            this._checkoutProductId = parseInt(btn.dataset.productId) || 0;
            this._openCheckoutModal(productName, productPrice, !!parentModal);
        });

        // Controles de quantidade
        document.getElementById('checkout-qty-minus')?.addEventListener('click', () => {
            const qtyEl = document.getElementById('checkout-qty');
            const val   = Math.max(1, parseInt(qtyEl.value) - 1);
            qtyEl.value = val;
            this._updateCheckoutTotal(this._checkoutPrice, val);
        });

        document.getElementById('checkout-qty-plus')?.addEventListener('click', () => {
            const qtyEl = document.getElementById('checkout-qty');
            const val   = Math.min(99, parseInt(qtyEl.value) + 1);
            qtyEl.value = val;
            this._updateCheckoutTotal(this._checkoutPrice, val);
        });

        document.getElementById('checkout-qty')?.addEventListener('input', () => {
            const val = Math.max(1, parseInt(document.getElementById('checkout-qty').value) || 1);
            this._updateCheckoutTotal(this._checkoutPrice, val);
        });

        // Limpa estado de erro ao digitar
        ['checkout-nome', 'checkout-email', 'checkout-telefone', 'checkout-cep', 'checkout-numero'].forEach(id => {
            document.getElementById(id)?.addEventListener('input', () => {
                document.getElementById(id)?.classList.remove('is-invalid');
            });
        });

        // Máscara de CEP (00000-000)
        document.getElementById('checkout-cep')?.addEventListener('input', (e) => {
            let v = e.target.value.replace(/\D/g, '').slice(0, 8);
            if (v.length > 5) v = v.slice(0, 5) + '-' + v.slice(5);
            e.target.value = v;
        });

        // Busca ViaCEP ao sair do campo ou clicar no botão
        const buscarCep = () => this._buscarCep();
        document.getElementById('checkout-cep')?.addEventListener('blur', buscarCep);
        document.getElementById('checkout-cep-btn')?.addEventListener('click', buscarCep);

        // Limpa o Brick ao fechar o modal
        document.getElementById('checkoutModal')?.addEventListener('hidden.bs.modal', () => {
            this._unmountBrick();
        });

        // Botão: redirecionar para o Mercado Pago (fluxo original)
        document.getElementById('checkout-submit-redirect')?.addEventListener('click', () => {
            this._doCheckoutSubmit('redirect');
        });

        // Botão: pagar aqui mesmo (Checkout Bricks)
        document.getElementById('checkout-submit-bricks')?.addEventListener('click', () => {
            this._doCheckoutSubmit('bricks');
        });

        // Link dentro do Bricks: volta para o fluxo de redirect
        document.getElementById('bricks-use-redirect')?.addEventListener('click', () => {
            if (this._currentInitPoint) {
                this._unmountBrick();
                bootstrap.Modal.getInstance(document.getElementById('checkoutModal'))?.hide();
                setTimeout(() => {
                    new bootstrap.Modal(document.getElementById('paymentRedirectModal')).show();
                    window.location.href = this._currentInitPoint;
                }, 400);
            }
        });
    }

    _validateCheckoutForm() {
        const required = [
            { id: 'checkout-nome',     value: document.getElementById('checkout-nome')?.value.trim() },
            { id: 'checkout-email',    value: document.getElementById('checkout-email')?.value.trim() },
            { id: 'checkout-telefone', value: document.getElementById('checkout-telefone')?.value.trim() },
            { id: 'checkout-cep',      value: document.getElementById('checkout-cep')?.value.replace(/\D/g,'') },
            { id: 'checkout-numero',   value: document.getElementById('checkout-numero')?.value.trim() },
        ];
        let valid = true;
        required.forEach(({ id, value }) => {
            const el = document.getElementById(id);
            if (!value) { el?.classList.add('is-invalid'); valid = false; }
        });
        if (!valid) document.querySelector('#checkoutForm .is-invalid')?.focus();
        return valid;
    }

    async _doCheckoutSubmit(mode) {
        if (!this._selectedFrete) {
            const alertEl = document.getElementById('checkout-error-msg');
            if (alertEl) {
                alertEl.textContent = 'Selecione uma opção de frete para continuar.';
                alertEl.classList.remove('d-none');
                setTimeout(() => alertEl.classList.add('d-none'), 6000);
            }
            return;
        }
        if (!this._validateCheckoutForm()) return;

        const btns = ['checkout-submit-redirect', 'checkout-submit-bricks']
            .map(id => document.getElementById(id)).filter(Boolean);
        btns.forEach(b => b.disabled = true);

        const qty = parseInt(document.getElementById('checkout-qty').value) || 1;
        const g   = id => document.getElementById(id)?.value.trim() ?? '';

        try {
            // 1. Grava o pedido no banco
            const rPedido = await fetch('backend/api/pedidos.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    nome_comprador:     g('checkout-nome'),
                    email_comprador:    g('checkout-email'),
                    telefone_comprador: g('checkout-telefone'),
                    cep:                g('checkout-cep'),
                    endereco:           g('checkout-endereco'),
                    numero:             g('checkout-numero'),
                    complemento:        g('checkout-complemento'),
                    bairro:             g('checkout-bairro'),
                    cidade:             g('checkout-cidade'),
                    estado:             g('checkout-estado'),
                    produto_id:         this._checkoutProductId,
                    quantidade:         qty,
                    frete_escolhido:    this._selectedFrete || null,
                }),
            });
            if (!rPedido.ok) {
                const err = await rPedido.json().catch(() => ({}));
                throw new Error(err.erro || 'Erro ao criar pedido.');
            }
            const pedido = await rPedido.json();

            // 2. Cria a preference no Mercado Pago (necessário para ambos os modos)
            const rPagamento = await fetch('backend/api/pagamento.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pedido_id: pedido.pedido_id }),
            });
            if (!rPagamento.ok) {
                const err = await rPagamento.json().catch(() => ({}));
                throw new Error(err.erro || 'Erro ao criar pagamento.');
            }
            const mp = await rPagamento.json();

            this._currentInitPoint = mp.init_point;
            this._currentPedidoId  = pedido.pedido_id;
            this._currentToken     = pedido.token;

            if (mode === 'redirect') {
                bootstrap.Modal.getInstance(document.getElementById('checkoutModal'))?.hide();
                await new Promise(r => setTimeout(r, 400));
                new bootstrap.Modal(document.getElementById('paymentRedirectModal')).show();
                window.location.href = mp.init_point;
            } else {
                // Armazena dados do comprador para o modal de resultado
                this._buyerName  = g('checkout-nome');
                this._buyerEmail = g('checkout-email');
                // Popula resumo do pedido visível durante o Brick
                const _fmt  = v => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                const _qty  = parseInt(document.getElementById('checkout-qty').value) || 1;
                const _sub  = this._checkoutPrice * _qty;
                const _frt  = this._selectedFrete ? (this._selectedFrete.preco || 0) : 0;
                const _pnome = document.getElementById('checkout-product-name').textContent;
                document.getElementById('bricks-summary-product').textContent   = `${_pnome} × ${_qty}`;
                document.getElementById('bricks-summary-subtotal').textContent  = _fmt(_sub);
                document.getElementById('bricks-summary-frete-nome').textContent = this._selectedFrete
                    ? `${this._selectedFrete.transportadora} ${this._selectedFrete.nome}` : 'Frete';
                document.getElementById('bricks-summary-frete-val').textContent = _frt === 0 ? 'Grátis' : _fmt(_frt);
                document.getElementById('bricks-summary-total').textContent     = _fmt(pedido.total);
                // Esconde formulário e footer, renderiza o Brick
                document.getElementById('checkoutForm').classList.add('d-none');
                document.getElementById('checkout-footer').classList.add('d-none');
                document.getElementById('bricks-section').classList.remove('d-none');
                await this._renderBrick(mp.preference_id, g('checkout-email'), pedido.total);
            }

        } catch (err) {
            console.error('Checkout error:', err);
            btns.forEach(b => b.disabled = false);
            const alertEl = document.getElementById('checkout-error-msg');
            if (alertEl) {
                alertEl.textContent = err.message || 'Erro inesperado. Tente novamente.';
                alertEl.classList.remove('d-none');
                setTimeout(() => alertEl.classList.add('d-none'), 6000);
            }
        }
    }

    async _renderBrick(preferenceId, email, amount) {
        // Carrega o MP SDK sob demanda — não bloqueia o carregamento inicial da página
        if (!window.MercadoPago) {
            await new Promise((resolve, reject) => {
                const s = document.createElement('script');
                s.src = 'https://sdk.mercadopago.com/js/v2';
                s.onload = resolve;
                s.onerror = () => reject(new Error('Falha ao carregar SDK do Mercado Pago.'));
                document.head.appendChild(s);
            });
        }

        const cfgResp = await fetch('backend/api/public-config.php');
        const cfg     = await cfgResp.json();

        const isDark  = document.body.classList.contains('dark-mode');
        const mpSDK   = new MercadoPago(cfg.mp_public_key, { locale: 'pt-BR' });
        const builder = mpSDK.bricks();

        this._bricksInstance = await builder.create('payment', 'bricks-container', {
            initialization: {
                amount,
                payer: { email, entityType: 'individual' },
            },
            customization: {
                paymentMethods: {
                    creditCard:   'all',
                    debitCard:    'all',
                    ticket:       'all',
                    bankTransfer: 'all',
                },
                visual: {
                    style: { theme: isDark ? 'dark' : 'default' },
                },
            },
            callbacks: {
                onReady: () => {},
                onError: (error) => {
                    console.error('Brick error:', error);
                    if (!this._brickErrorHandled && error.type !== 'recoverable_error') {
                        this._showPaymentError('Ocorreu um erro ao processar o pagamento.<br>Tente novamente em instantes.');
                    }
                    this._brickErrorHandled = false;
                },
                onSubmit: async ({ formData }) => {
                    const resp = await fetch('backend/api/processar-pagamento.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            pedido_id: this._currentPedidoId,
                            form_data: formData,
                        }),
                    });
                    const result = await resp.json();
                    if (!resp.ok) {
                        const msg = result.erro || 'Erro ao processar pagamento. Tente novamente.';
                        this._brickErrorHandled = true;
                        this._showPaymentError(msg);
                        throw new Error(msg);
                    }
                    this._showBricksResult(result);
                    return result;
                },
            },
        });
    }

    _showBricksResult(result) {
        const firstName = (this._buyerName  || '').split(' ')[0];
        const email     =  this._buyerEmail || '';

        const iconEl    = document.getElementById('bricks-result-icon');
        const titleEl   = document.getElementById('bricks-result-title');
        const msgEl     = document.getElementById('bricks-result-msg');
        const trackBtn  = document.getElementById('bricks-result-track-btn');

        if (result.status === 'approved') {
            iconEl.innerHTML    = '<i class="fas fa-circle-check text-success" style="font-size:3rem;"></i>';
            titleEl.textContent = 'Pagamento aprovado!';
            msgEl.innerHTML     = `Obrigado${firstName ? ', <strong>' + firstName + '</strong>' : ''}! Seu pedido foi confirmado.<br>Enviamos os detalhes para <strong>${email}</strong>.`;
            trackBtn.href       = `acompanhar.html?pedido=${result.pedido_id}&token=${result.token}`;
            trackBtn.classList.remove('d-none');
        } else if (result.status === 'rejected') {
            const detalhe = this._traduzirStatusDetail(result.status_detail);
            iconEl.innerHTML    = '<i class="fas fa-circle-xmark text-danger" style="font-size:3rem;"></i>';
            titleEl.textContent = 'Pagamento não aprovado';
            msgEl.innerHTML     = `${firstName ? '<strong>' + firstName + '</strong>, seu' : 'Seu'} pagamento foi recusado.<br><span class="text-muted small">${detalhe}</span>`;
            trackBtn.classList.add('d-none');
        } else {
            iconEl.innerHTML    = '<i class="fas fa-clock text-warning" style="font-size:3rem;"></i>';
            titleEl.textContent = 'Pagamento em análise';
            msgEl.innerHTML     = `${firstName ? '<strong>' + firstName + '</strong>, seu' : 'Seu'} pedido foi recebido!<br>Assim que confirmado, enviaremos um e-mail para <strong>${email}</strong>.`;
            trackBtn.classList.add('d-none');
        }

        bootstrap.Modal.getInstance(document.getElementById('checkoutModal'))?.hide();
        setTimeout(() => {
            new bootstrap.Modal(document.getElementById('bricksResultModal')).show();
        }, 450);
    }

    _traduzirStatusDetail(detail) {
        const map = {
            cc_rejected_bad_filled_card_number: 'Número do cartão inválido. Verifique e tente novamente.',
            cc_rejected_bad_filled_date:        'Data de validade inválida. Verifique e tente novamente.',
            cc_rejected_bad_filled_other:       'Dado do cartão inválido. Verifique as informações.',
            cc_rejected_bad_filled_security_code:'Código de segurança (CVV) inválido.',
            cc_rejected_blacklist:              'Cartão bloqueado. Entre em contato com seu banco.',
            cc_rejected_call_for_authorize:     'Cartão requer autorização. Ligue para seu banco e tente novamente.',
            cc_rejected_card_disabled:          'Cartão desabilitado. Entre em contato com seu banco.',
            cc_rejected_duplicated_payment:     'Pagamento duplicado. Já existe uma cobrança recente com este cartão.',
            cc_rejected_high_risk:              'Pagamento recusado por segurança. Tente outro cartão ou meio de pagamento.',
            cc_rejected_insufficient_amount:    'Saldo insuficiente. Verifique o limite disponível.',
            cc_rejected_invalid_installments:   'Número de parcelas não suportado para este cartão.',
            cc_rejected_max_attempts:           'Limite de tentativas atingido. Aguarde e tente com outro cartão.',
            cc_rejected_other_reason:           'Recusado pelo banco. Entre em contato com seu banco.',
        };
        return map[detail] || 'Verifique os dados do cartão ou tente outro meio de pagamento.';
    }

    _showPaymentError(message) {
        const iconEl   = document.getElementById('bricks-result-icon');
        const titleEl  = document.getElementById('bricks-result-title');
        const msgEl    = document.getElementById('bricks-result-msg');
        const trackBtn = document.getElementById('bricks-result-track-btn');

        iconEl.innerHTML    = '<i class="fas fa-circle-xmark text-danger" style="font-size:3rem;"></i>';
        titleEl.textContent = 'Pagamento não processado';
        msgEl.innerHTML     = message || 'Ocorreu um erro ao processar o pagamento.<br>Tente novamente em instantes.';
        trackBtn.classList.add('d-none');

        bootstrap.Modal.getInstance(document.getElementById('checkoutModal'))?.hide();
        setTimeout(() => {
            new bootstrap.Modal(document.getElementById('bricksResultModal')).show();
        }, 450);
    }

    _unmountBrick() {
        this._brickErrorHandled = false;
        this._bricksInstance?.unmount();
        this._bricksInstance   = null;
        this._currentPedidoId  = null;
        this._currentToken     = null;
        this._currentInitPoint = null;
        document.getElementById('checkoutForm')?.classList.remove('d-none');
        document.getElementById('checkout-footer')?.classList.remove('d-none');
        document.getElementById('bricks-section')?.classList.add('d-none');
        const c = document.getElementById('bricks-container');
        if (c) c.innerHTML = '';
    }

    async _buscarCep() {
        const cepEl = document.getElementById('checkout-cep');
        const cep   = cepEl?.value.replace(/\D/g, '');
        if (!cep || cep.length !== 8) return;

        const btn = document.getElementById('checkout-cep-btn');
        if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            const r    = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
            const data = await r.json();

            if (data.erro) {
                cepEl.classList.add('is-invalid');
                cepEl.nextElementSibling?.nextElementSibling || (cepEl.closest('.col-sm-5')?.querySelector('.invalid-feedback') && (cepEl.closest('.col-sm-5').querySelector('.invalid-feedback').textContent = 'CEP não encontrado.'));
            } else {
                document.getElementById('checkout-endereco').value = data.logradouro || '';
                document.getElementById('checkout-bairro').value   = data.bairro     || '';
                document.getElementById('checkout-cidade').value   = data.localidade || '';
                document.getElementById('checkout-estado').value   = data.uf         || '';
                cepEl.classList.remove('is-invalid');
                localStorage.setItem('psp_cep_entrega', cepEl.value);
                document.getElementById('checkout-numero')?.focus();
                this._calcularFreteCheckout(cep);
            }
        } catch (_) {
            // falha silenciosa — usuário pode preencher manualmente
        } finally {
            if (btn) btn.innerHTML = '<i class="fas fa-search"></i>';
        }
    }

    _openCheckoutModal(productName, price, withDelay) {
        this._unmountBrick();
        this._selectedFrete = null;
        document.getElementById('checkout-product-name').textContent = productName;
        document.getElementById('checkout-qty').value = 1;
        this._updateCheckoutTotal(price, 1);

        // Limpa campos e erros anteriores
        const allFields = ['checkout-nome','checkout-email','checkout-telefone',
                           'checkout-cep','checkout-numero','checkout-complemento',
                           'checkout-endereco','checkout-bairro','checkout-cidade','checkout-estado'];
        allFields.forEach(id => {
            const el = document.getElementById(id);
            if (el) { el.value = ''; el.classList.remove('is-invalid'); }
        });

        const freteCheckout = document.getElementById('frete-resultado-checkout');
        if (freteCheckout) { freteCheckout.style.display = 'none'; freteCheckout.innerHTML = ''; }

        // Pré-preenche CEP salvo e dispara recálculo ao abrir
        const savedCep = localStorage.getItem('psp_cep_entrega');
        if (savedCep) {
            const cepEl = document.getElementById('checkout-cep');
            if (cepEl) cepEl.value = savedCep;
            document.getElementById('checkoutModal')?.addEventListener('shown.bs.modal',
                () => this._buscarCep(), { once: true });
        }

        setTimeout(() => {
            new bootstrap.Modal(document.getElementById('checkoutModal')).show();
        }, withDelay ? 450 : 0);
    }

    _updateCheckoutTotal(price, qty) {
        const subtotal = price * qty;
        const frete    = this._selectedFrete ? (this._selectedFrete.preco || 0) : null;
        const grand    = frete !== null ? subtotal + frete : subtotal;
        const fmt      = v => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

        document.getElementById('checkout-total').textContent     = fmt(subtotal);
        document.getElementById('checkout-btn-total').textContent = fmt(grand);

        const resumoEl = document.getElementById('checkout-resumo');
        if (resumoEl) {
            if (frete !== null) {
                document.getElementById('checkout-subtotal-val').textContent      = fmt(subtotal);
                const nomeTransp = `${this._selectedFrete.transportadora} ${this._selectedFrete.nome}`;
                document.getElementById('checkout-frete-nome-resumo').textContent = nomeTransp;
                document.getElementById('checkout-frete-val-resumo').textContent  =
                    frete === 0 ? 'Grátis' : fmt(frete);
                document.getElementById('checkout-grand-total').textContent       = fmt(grand);
                resumoEl.style.display = '';
            } else {
                resumoEl.style.display = 'none';
            }
        }
        this._updatePaymentBtns();
    }

    _updatePaymentBtns() {
        const hasFreight = !!this._selectedFrete;
        ['checkout-submit-redirect', 'checkout-submit-bricks'].forEach(id => {
            const btn = document.getElementById(id);
            if (btn) btn.disabled = !hasFreight;
        });
        const warnEl = document.getElementById('checkout-frete-aviso');
        if (warnEl) warnEl.style.display = hasFreight ? 'none' : '';
    }

    // ── LIGHTBOX ─────────────────────────────────────────────────────────────

    setupImageLightbox() {
        const overlay   = document.getElementById('lightboxOverlay');
        const lbImg     = document.getElementById('lightbox-img');
        const lbCaption = document.getElementById('lightbox-caption');
        if (!overlay) return;

        const open = (src, alt) => {
            lbImg.src           = src;
            lbImg.alt           = alt;
            lbCaption.textContent = alt || '';
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        };

        const close = () => {
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        };

        // Clique em imagem de card, carrossel ou modal de produto
        document.addEventListener('click', (e) => {
            const img = e.target.closest(
                'img.card-img-top, img.carousel-img, img.modal-product-image'
            );
            if (!img) return;
            open(img.src, img.alt);
        });

        // Fecha ao clicar no fundo escuro
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) close();
        });

        // Fecha pelo botão X
        document.getElementById('lightboxClose')?.addEventListener('click', close);

        // Fecha com Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && overlay.classList.contains('active')) close();
        });
    }

    // ── PRODUTOS — renderiza cards, modais e filtros a partir da API ─────────

    async renderProducts() {
        try {
            const ctrl = new AbortController();
            const timer = setTimeout(() => ctrl.abort(), 10000);
            const [prodRes, catRes] = await Promise.all([
                fetch('backend/api/produtos.php',  { signal: ctrl.signal }),
                fetch('backend/api/categorias.php', { signal: ctrl.signal }),
            ]);
            clearTimeout(timer);
            const produtos   = await prodRes.json();
            const categorias = catRes.ok ? await catRes.json() : [];

            // Mapa slug → nome para lookup nos cards
            const categoryMap = Object.fromEntries(categorias.map(c => [c.slug, c.nome]));

            const grid   = document.getElementById('products-grid');
            const modals = document.getElementById('product-modals');
            if (!grid || !modals) return;

            let cardsHtml  = '';
            let modalsHtml = '';

            // Gera botões de filtro dinamicamente
            const filtersEl = document.getElementById('product-filters');
            if (filtersEl && categorias.length) {
                filtersEl.innerHTML =
                    `<button class="filter-btn active" data-filter="all">Todos</button>` +
                    categorias.map(c =>
                        `<button class="filter-btn" data-filter="${c.slug}">${c.nome}</button>`
                    ).join('');
            }

            produtos.forEach((p, i) => {
                const label  = categoryMap[p.categoria] || p.categoria;
                const preco  = p.preco.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                const delay  = (i % 3) * 100;

                // Imagem principal: prioriza array imagens, cai para campo imagem legado
                const imagens     = Array.isArray(p.imagens) ? p.imagens : [];
                const imgPrincipal = imagens.find(img => img.principal) || imagens[0];
                const imgSrc      = imgPrincipal ? imgPrincipal.caminho : (p.imagem || '');

                const imgTag = imgSrc
                    ? `<img src="${imgSrc}" class="card-img-top" alt="${p.nome}" loading="lazy">`
                    : `<div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height:180px"><i class="fas fa-image fa-3x text-muted"></i></div>`;

                // Área de mídia do modal: carousel se múltiplas imagens, senão imagem simples
                let modalMedia;
                if (imagens.length > 1) {
                    const carouselId = `carousel-prod-${p.id}`;
                    const slides = imagens.map((img, idx) => `
                        <div class="carousel-item ${idx === 0 ? 'active' : ''}">
                            <img src="${img.caminho}" class="d-block w-100 carousel-img" alt="${p.nome}" loading="lazy"
                                 style="max-height:280px;object-fit:contain;background:#f8f9fa;border-radius:6px;">
                        </div>`).join('');
                    const indicators = imagens.map((_, idx) => `
                        <button type="button" data-bs-target="#${carouselId}" data-bs-slide-to="${idx}"
                                class="${idx === 0 ? 'active' : ''}" style="background-color:#274185"></button>`).join('');
                    modalMedia = `
                        <div id="${carouselId}" class="carousel slide" data-bs-ride="false">
                            <div class="carousel-indicators">${indicators}</div>
                            <div class="carousel-inner">${slides}</div>
                            <button class="carousel-control-prev" type="button" data-bs-target="#${carouselId}" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon"></span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#${carouselId}" data-bs-slide="next">
                                <span class="carousel-control-next-icon"></span>
                            </button>
                        </div>`;
                } else if (imgSrc) {
                    modalMedia = `<img src="${imgSrc}" class="modal-product-image" alt="${p.nome}" loading="lazy">`;
                } else {
                    modalMedia = '';
                }

                // Código interno (com botão de copiar, reutilizado no card e no modal)
                const codigoHtml = p.codigo_interno
                    ? `<div class="text-muted small mb-2 d-flex align-items-center gap-1">
                           <i class="fas fa-barcode me-1"></i>${p.codigo_interno}
                           <button type="button" class="btn btn-link btn-sm p-0 btn-copy-code"
                                   data-codigo="${p.codigo_interno}" title="Copiar código">
                               <i class="fas fa-copy"></i>
                           </button>
                       </div>`
                    : '';

                // Ações de compartilhamento (WhatsApp + Web Share/copiar link) — exibidas no modal de detalhes
                const shareActionsHtml = `
                    <div class="d-flex gap-2 mb-3 product-share-actions">
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-whatsapp-share"
                                data-product-id="${p.id}" data-product-name="${p.nome}" data-product-code="${p.codigo_interno || ''}"
                                title="Enviar no WhatsApp">
                            <i class="fab fa-whatsapp"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-share-product"
                                data-product-id="${p.id}" data-product-name="${p.nome}" data-product-code="${p.codigo_interno || ''}"
                                title="Compartilhar produto">
                            <i class="fas fa-share-nodes"></i>
                        </button>
                    </div>`;

                // Indicador de disponibilidade (estoque = 0 → esgotado)
                const estoqueHtml = p.estoque > 0
                    ? `<span class="product-badge badge-disponivel">Em estoque</span>`
                    : `<span class="product-badge badge-esgotado">Esgotado</span>`;

                // Link data sheet
                const datasheetHtml = p.datasheet
                    ? `<a href="${p.datasheet}" target="_blank" class="btn btn-sm btn-outline-secondary me-1">
                           <i class="fas fa-file-pdf me-1 text-danger"></i> Data Sheet
                       </a>`
                    : '';

                // Especificação técnica (Markdown → HTML)
                const especHtml = p.especificacao_tecnica && typeof marked !== 'undefined'
                    ? `<hr class="my-3">
                       <h6 class="fw-semibold mb-2"><i class="fas fa-list-ul me-1 text-muted"></i> Especificação Técnica</h6>
                       <div class="spec-content small">${marked.parse(p.especificacao_tecnica)}</div>`
                    : '';

                cardsHtml += `
                <div class="col-md-4 mb-4 product-col" data-category="${p.categoria}"
                     data-nome="${p.nome}" data-preco="${p.preco}" data-estoque="${p.estoque}" data-codigo="${p.codigo_interno || ''}">
                    <div class="card product-card">
                        ${imgTag}
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="product-badge badge-${p.categoria}">${label}</span>
                                ${estoqueHtml}
                            </div>
                            <h5 class="card-title">${p.nome}</h5>
                            ${codigoHtml}
                            <p class="card-text">${p.descricao || ''}</p>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span class="product-price">${preco}</span>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm"
                                            data-bs-toggle="modal" data-bs-target="#productModal${p.id}">
                                        Detalhes
                                    </button>
                                    ${p.estoque > 0
                                        ? `<button type="button" class="btn btn-success btn-sm btn-buy"
                                                data-product-id="${p.id}"
                                                data-product-name="${p.nome}"
                                                data-product-price="${p.preco}"
                                                title="Comprar">
                                            <i class="fas fa-cart-shopping"></i>
                                           </button>`
                                        : `<button type="button" class="btn btn-secondary btn-sm" disabled title="Sem estoque">
                                            <i class="fas fa-ban"></i>
                                           </button>`}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`;

                modalsHtml += `
                <div class="modal fade" id="productModal${p.id}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">${p.nome} — Detalhes</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6 modal-image-container">${modalMedia}</div>
                                    <div class="col-md-6">
                                        <h4>${p.nome}</h4>
                                        ${codigoHtml}
                                        ${shareActionsHtml}
                                        <span class="product-badge badge-${p.categoria} mb-2 d-inline-block">${label}</span>
                                        <p>${p.descricao || ''}</p>
                                        ${p.estoque > 0
                                            ? `<span class="badge bg-success">Em estoque (${p.estoque} un.)</span>`
                                            : `<span class="badge bg-danger">Fora de estoque</span>`}
                                        <div class="frete-calc" data-produto-id="${p.id}">
                                            <p class="fw-semibold small text-muted mb-2">
                                                <i class="fas fa-truck me-1"></i>Calcular prazo de entrega
                                            </p>
                                            <div class="d-flex gap-2">
                                                <input type="text" class="form-control form-control-sm frete-cep-input"
                                                       placeholder="00000-000" maxlength="9"
                                                       aria-label="CEP para calcular frete">
                                                <button type="button" class="btn btn-sm btn-outline-primary frete-btn-calcular">
                                                    Calcular
                                                </button>
                                            </div>
                                            <a href="https://buscacepinter.correios.com.br/app/endereco/index.php"
                                               target="_blank" rel="noopener noreferrer"
                                               class="text-muted small text-decoration-none d-inline-block mt-1">
                                                <i class="fas fa-magnifying-glass me-1"></i>Não sei meu CEP
                                            </a>
                                            <div class="frete-resultado"></div>
                                        </div>
                                        ${especHtml}
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <span class="me-auto product-price-modal">${preco}</span>
                                ${datasheetHtml}
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                                <button type="button" class="btn btn-outline-primary solicitar-orcamento" data-product="${p.nome}">
                                    Solicitar Orçamento
                                </button>
                                ${p.estoque > 0
                                    ? `<button type="button" class="btn btn-success btn-buy"
                                            data-product-id="${p.id}"
                                            data-product-name="${p.nome}"
                                            data-product-price="${p.preco}">
                                        <i class="fas fa-cart-shopping me-1"></i> Comprar Agora
                                       </button>`
                                    : `<button type="button" class="btn btn-secondary" disabled>
                                        <i class="fas fa-ban me-1"></i> Fora de estoque
                                       </button>`}
                            </div>
                        </div>
                    </div>
                </div>`;
            });

            grid.innerHTML   = cardsHtml   || '<p class="text-center text-muted col-12">Nenhum produto disponível.</p>';
            modals.innerHTML = modalsHtml;

            if (typeof AOS !== 'undefined') AOS.refresh();

        } catch (_) { /* mantém conteúdo estático do HTML se API falhar */ }
        finally {
            // Roda tanto no sucesso (produtos dinâmicos) quanto na falha (fallback estático já no DOM) —
            // só neste ponto o grid/modais estão em seu estado final e é seguro ler/abrir por id.
            this._openDeepLinkProduct();
            this._applyCurrentToolbarState();
        }
    }

    // ── RETORNO DO MERCADO PAGO ───────────────────────────────────────────────

    handleRetornoMP() {
        const params  = new URLSearchParams(window.location.search);
        const status  = params.get('pagamento');
        if (!status) return;

        // Limpa os parâmetros da URL sem recarregar a página
        window.history.replaceState({}, document.title, window.location.pathname);

        if (status === 'recusado') {
            this.showErrorModal('Pagamento não aprovado. Verifique os dados do cartão ou escolha outra forma de pagamento e tente novamente.');
        } else if (status === 'pendente') {
            this._showMpPendente();
        }
    }

    _showMpPendente() {
        const modal   = document.getElementById('confirmationModal');
        if (!modal) return;

        const title   = modal.querySelector('.modal-title');
        const icon    = modal.querySelector('.modal-body i');
        const heading = modal.querySelector('.modal-body h4');
        const text    = modal.querySelector('.modal-body p');

        if (title)   title.textContent   = 'Pagamento em análise';
        if (icon)    { icon.className    = 'fas fa-clock fa-3x text-warning mb-3'; }
        if (heading) heading.textContent = 'Seu pagamento está pendente';
        if (text)    text.textContent    = 'O pagamento está sendo processado. Você receberá um e-mail assim que for confirmado.';

        new bootstrap.Modal(modal).show();
    }

    // ── FRETE / PRAZO DE ENTREGA ─────────────────────────────────────────────

    setupFrete() {
        // Máscara de CEP nos inputs dos modais de produto (event delegation)
        document.addEventListener('input', (e) => {
            if (!e.target.classList.contains('frete-cep-input')) return;
            let v = e.target.value.replace(/\D/g, '').slice(0, 8);
            if (v.length > 5) v = v.slice(0, 5) + '-' + v.slice(5);
            e.target.value = v;
        });

        // Enter dispara cálculo
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter' || !e.target.classList.contains('frete-cep-input')) return;
            e.preventDefault();
            e.target.closest('.frete-calc')?.querySelector('.frete-btn-calcular')?.click();
        });

        // Botão Calcular
        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('.frete-btn-calcular');
            if (!btn) return;

            const container = btn.closest('.frete-calc');
            const produtoId = parseInt(container?.dataset.produtoId) || 0;
            const input     = container?.querySelector('.frete-cep-input');
            const cep       = input?.value.replace(/\D/g, '') || '';
            const resultEl  = container?.querySelector('.frete-resultado');

            if (cep.length !== 8) {
                if (resultEl) resultEl.innerHTML = '<p class="text-danger small mt-2 mb-0"><i class="fas fa-circle-exclamation me-1"></i>CEP inválido. Digite 8 dígitos.</p>';
                return;
            }

            localStorage.setItem('psp_cep_entrega', input.value);
            await this._calcularFrete(produtoId, cep, resultEl, btn);
        });

        // Pré-preenche CEP salvo ao abrir modais de produto
        document.addEventListener('shown.bs.modal', (e) => {
            const input = e.target.querySelector('.frete-cep-input');
            if (!input) return;
            const saved = localStorage.getItem('psp_cep_entrega');
            if (saved) input.value = saved;
        });
    }

    async _calcularFrete(produtoId, cep, resultEl, btnEl) {
        if (!resultEl) return;

        const btnOriginal = btnEl?.innerHTML;
        if (btnEl) { btnEl.disabled = true; btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; }
        resultEl.innerHTML = '<p class="text-muted small mt-2 mb-0"><i class="fas fa-spinner fa-spin me-1"></i>Calculando...</p>';

        try {
            const resp = await fetch('backend/api/frete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ produto_id: produtoId, cep_destino: cep }),
            });
            const data = await resp.json();

            if (!resp.ok || !data.ok) {
                resultEl.innerHTML = `<p class="text-danger small mt-2 mb-0"><i class="fas fa-circle-exclamation me-1"></i>${data.erro || 'Não foi possível calcular o frete para este CEP. Verifique e tente novamente.'}</p>`;
                return;
            }

            this._renderFrete(data.servicos, data.cep, resultEl);

        } catch (_) {
            resultEl.innerHTML = '<p class="text-danger small mt-2 mb-0"><i class="fas fa-circle-exclamation me-1"></i>Não foi possível calcular o frete para este CEP. Verifique e tente novamente.</p>';
        } finally {
            if (btnEl) { btnEl.disabled = false; btnEl.innerHTML = btnOriginal; }
        }
    }

    async _calcularFreteCheckout(cep) {
        const resultEl = document.getElementById('frete-resultado-checkout');
        if (!resultEl || !this._checkoutProductId) return;

        resultEl.style.display = 'block';
        resultEl.innerHTML = '<p class="text-muted small mt-2 mb-0"><i class="fas fa-spinner fa-spin me-1"></i>Calculando prazo de entrega...</p>';
        this._selectedFrete = null;

        try {
            const resp = await fetch('backend/api/frete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ produto_id: this._checkoutProductId, cep_destino: cep }),
            });
            const data = await resp.json();

            if (!resp.ok || !data.ok) {
                resultEl.innerHTML = `<p class="text-danger small mt-2 mb-0"><i class="fas fa-circle-exclamation me-1"></i>${data.erro || 'Não foi possível calcular o frete para este CEP.'}</p>`;
                return;
            }

            localStorage.setItem('psp_cep_entrega', cep.replace(/(\d{5})(\d{3})/, '$1-$2'));
            this._renderFreteCheckout(data.servicos, data.cep, resultEl);

        } catch (_) {
            resultEl.innerHTML = '<p class="text-danger small mt-2 mb-0"><i class="fas fa-circle-exclamation me-1"></i>Não foi possível calcular o frete para este CEP.</p>';
        }
    }

    _alterarCepCheckout() {
        const resultEl = document.getElementById('frete-resultado-checkout');
        if (resultEl) { resultEl.style.display = 'none'; resultEl.innerHTML = ''; }
        this._selectedFrete = null;
        this._updateCheckoutTotal(
            this._checkoutPrice,
            parseInt(document.getElementById('checkout-qty').value) || 1
        );
        const cepEl = document.getElementById('checkout-cep');
        if (cepEl) { cepEl.focus(); cepEl.select(); }
    }

    _renderFreteCheckout(servicos, cep, containerEl) {
        const hoje = new Date();
        hoje.setHours(0, 0, 0, 0);

        const MESES = ['janeiro','fevereiro','março','abril','maio','junho','julho',
                       'agosto','setembro','outubro','novembro','dezembro'];
        const fmt = d => `${d.getDate()} de ${MESES[d.getMonth()]}`;

        const linhas = servicos.map((s, idx) => {
            const dataMin  = this._adicionarDiasUteis(hoje, s.prazo_min);
            const dataMax  = this._adicionarDiasUteis(hoje, s.prazo_max);
            const mesmoDia = s.prazo_min === s.prazo_max;

            const chegada = mesmoDia
                ? `Chegará até <strong>${fmt(dataMax)}</strong>`
                : `Chegará entre <strong>${fmt(dataMin)}</strong> e <strong>${fmt(dataMax)}</strong>`;

            const precoHtml = s.preco === 0
                ? '<span class="badge bg-success ms-1">Frete grátis</span>'
                : `<span class="frete-preco">${s.preco.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</span>`;

            const logoHtml = s.logo
                ? `<img src="${s.logo}" alt="${s.transportadora}" class="frete-logo">`
                : `<span class="frete-logo-placeholder"><i class="fas fa-truck"></i></span>`;

            const isFirst = idx === 0;
            return `
            <div class="frete-opcao frete-opcao--selec${isFirst ? ' frete-opcao--ativo' : ''}"
                 data-frete='${JSON.stringify(s).replace(/'/g, '&#39;')}'>
                <div class="frete-opcao-header">
                    <span class="frete-selec-check"><i class="fas fa-circle-check"></i></span>
                    ${logoHtml}
                    <span class="frete-nome">${s.nome}</span>
                    ${precoHtml}
                </div>
                <div class="frete-chegada">
                    <i class="fas fa-calendar-days me-1"></i>${chegada}
                </div>
            </div>`;
        }).join('');

        containerEl.innerHTML = `
            <div class="frete-resultado-box">
                <p class="frete-cep-label mb-2 d-flex align-items-center justify-content-between">
                    <span><i class="fas fa-location-dot me-1"></i>CEP ${cep} — escolha uma opção:</span>
                    <button type="button" class="btn btn-link btn-sm p-0 text-muted text-decoration-none frete-alterar-cep-btn" style="font-size:0.78rem;">
                        <i class="fas fa-pencil me-1"></i>Alterar CEP
                    </button>
                </p>
                ${linhas}
            </div>`;

        // Botão Alterar CEP
        containerEl.querySelector('.frete-alterar-cep-btn')?.addEventListener('click', (e) => {
            e.preventDefault();
            this._alterarCepCheckout();
        });

        // Seleciona a primeira opção por padrão
        if (servicos.length > 0) {
            this._selectedFrete = servicos[0];
            this._updateCheckoutTotal(
                this._checkoutPrice,
                parseInt(document.getElementById('checkout-qty').value) || 1
            );
        }

        // Listener de seleção
        containerEl.querySelectorAll('.frete-opcao--selec').forEach(el => {
            el.addEventListener('click', () => {
                containerEl.querySelectorAll('.frete-opcao--selec').forEach(o => o.classList.remove('frete-opcao--ativo'));
                el.classList.add('frete-opcao--ativo');
                try { this._selectedFrete = JSON.parse(el.dataset.frete); } catch (_) {}
                this._updateCheckoutTotal(
                    this._checkoutPrice,
                    parseInt(document.getElementById('checkout-qty').value) || 1
                );
            });
        });
    }

    _renderFrete(servicos, cep, containerEl) {
        const hoje = new Date();
        hoje.setHours(0, 0, 0, 0);

        const MESES = ['janeiro','fevereiro','março','abril','maio','junho','julho',
                       'agosto','setembro','outubro','novembro','dezembro'];
        const fmt = d => `${d.getDate()} de ${MESES[d.getMonth()]}`;

        const linhas = servicos.map(s => {
            const dataMin  = this._adicionarDiasUteis(hoje, s.prazo_min);
            const dataMax  = this._adicionarDiasUteis(hoje, s.prazo_max);
            const mesmoDia = s.prazo_min === s.prazo_max;

            const chegada = mesmoDia
                ? `Chegará até <strong>${fmt(dataMax)}</strong>`
                : `Chegará entre <strong>${fmt(dataMin)}</strong> e <strong>${fmt(dataMax)}</strong>`;

            const precoHtml = s.preco === 0
                ? '<span class="badge bg-success ms-1">Frete grátis</span>'
                : `<span class="frete-preco">${s.preco.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</span>`;

            const logoHtml = s.logo
                ? `<img src="${s.logo}" alt="${s.transportadora}" class="frete-logo">`
                : `<span class="frete-logo-placeholder"><i class="fas fa-truck"></i></span>`;

            return `
            <div class="frete-opcao">
                <div class="frete-opcao-header">
                    ${logoHtml}
                    <span class="frete-nome">${s.nome}</span>
                    ${precoHtml}
                </div>
                <div class="frete-chegada">
                    <i class="fas fa-calendar-days me-1"></i>${chegada}
                </div>
            </div>`;
        }).join('');

        containerEl.innerHTML = `
            <div class="frete-resultado-box">
                <p class="frete-cep-label mb-2">
                    <i class="fas fa-location-dot me-1"></i>Entrega para o CEP ${cep}
                </p>
                ${linhas}
            </div>`;
    }

    _calcularEaster(year) {
        const a = year % 19, b = Math.floor(year / 100), c = year % 100;
        const d = Math.floor(b / 4), e = b % 4, f = Math.floor((b + 8) / 25);
        const g = Math.floor((b - f + 1) / 3), h = (19 * a + b - d - g + 15) % 30;
        const i = Math.floor(c / 4), k = c % 4, l = (32 + 2 * e + 2 * i - h - k) % 7;
        const m = Math.floor((a + 11 * h + 22 * l) / 451);
        return new Date(year, Math.floor((h + l - 7 * m + 114) / 31) - 1, ((h + l - 7 * m + 114) % 31) + 1);
    }

    _feriadosBR(year) {
        const easter  = this._calcularEaster(year);
        const addDays = (d, n) => { const r = new Date(d); r.setDate(r.getDate() + n); return r; };
        const ymd     = d => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
        return new Set([
            `${year}-01-01`, `${year}-04-21`, `${year}-05-01`,
            `${year}-09-07`, `${year}-10-12`, `${year}-11-02`,
            `${year}-11-15`, `${year}-12-25`,
            ymd(addDays(easter, -2)),  // Sexta-Feira Santa
            ymd(addDays(easter, 60)), // Corpus Christi
        ]);
    }

    _adicionarDiasUteis(dataInicio, dias) {
        const ymd = d => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
        const feriados = new Set([
            ...this._feriadosBR(dataInicio.getFullYear()),
            ...this._feriadosBR(dataInicio.getFullYear() + 1),
        ]);
        let d = new Date(dataInicio);
        let contados = 0;
        while (contados < dias) {
            d.setDate(d.getDate() + 1);
            const dow = d.getDay();
            if (dow !== 0 && dow !== 6 && !feriados.has(ymd(d))) contados++;
        }
        return d;
    }

    _showPaymentComingSoon() {
        const modal   = document.getElementById('confirmationModal');
        const header  = modal.querySelector('.modal-header');
        const title   = modal.querySelector('.modal-title');
        const icon    = modal.querySelector('.modal-body .fa-check-circle');
        const heading = modal.querySelector('.modal-body h4');
        const text    = modal.querySelector('.modal-body p');
        const footerBtn = modal.querySelector('.modal-footer .btn');

        header.className    = 'modal-header bg-primary text-white';
        title.innerHTML     = '<i class="fas fa-rocket me-2"></i>Em breve!';
        if (icon)    icon.className    = 'fas fa-rocket text-primary';
        if (heading) { heading.textContent = 'Pagamento online em breve!'; heading.className = 'text-primary'; }
        if (text)    text.textContent  = 'Estamos integrando o Mercado Pago. Em breve você poderá comprar direto pelo site!';
        if (footerBtn) { footerBtn.className = 'btn btn-primary'; footerBtn.innerHTML = '<i class="fas fa-thumbs-up me-2"></i>Entendi'; }

        new bootstrap.Modal(modal).show();
    }
}

// Inicialização da aplicação quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    new App();
    AOS.init({ duration: 600, once: true, offset: 80 });
});