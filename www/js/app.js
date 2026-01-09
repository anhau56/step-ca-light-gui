/**
 * Step CA GUI - Client-side JavaScript
 */

// Theme management
const ThemeManager = {
    init() {
        const savedTheme = localStorage.getItem('theme') || 'light';
        this.setTheme(savedTheme);

        // Add toggle button listener if exists
        const toggleBtn = document.getElementById('theme-toggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => this.toggle());
            this.updateToggleIcon(savedTheme);
        }
    },

    setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
        this.updateToggleIcon(theme);
    },

    toggle() {
        const current = localStorage.getItem('theme') || 'light';
        const newTheme = current === 'light' ? 'dark' : 'light';
        this.setTheme(newTheme);
    },

    updateToggleIcon(theme) {
        const toggleBtn = document.getElementById('theme-toggle');
        if (toggleBtn) {
            toggleBtn.textContent = theme === 'light' ? '🌙' : '☀️';
            toggleBtn.title = theme === 'light' ? 'Switch to dark mode' : 'Switch to light mode';
        }
    }
};

// SAN management
const SANManager = {
    init() {
        const addBtn = document.getElementById('add-san');
        if (addBtn) {
            addBtn.addEventListener('click', () => this.addRow());
        }
    },

    addRow() {
        const container = document.getElementById('san-container');
        if (!container) return;

        const row = document.createElement('div');
        row.className = 'san-row';
        row.innerHTML = `
            <input type="text" name="san[]" placeholder="Additional DNS or IP">
            <button type="button" class="btn-remove" onclick="SANManager.removeRow(this)">✕</button>
        `;
        container.appendChild(row);
    },

    removeRow(btn) {
        btn.parentElement.remove();
    }
};

// Certificate search/filter
const CertSearch = {
    init() {
        const searchInput = document.getElementById('cert-search');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => this.filter(e.target.value));
        }
    },

    filter(query) {
        const cards = document.querySelectorAll('.cert-card');
        const resultsEl = document.getElementById('search-results');
        let visibleCount = 0;

        query = query.toLowerCase().trim();

        cards.forEach(card => {
            const name = card.querySelector('h2').textContent.toLowerCase();
            const matches = !query || name.includes(query);

            card.style.display = matches ? '' : 'none';
            if (matches) visibleCount++;
        });

        if (resultsEl) {
            if (query) {
                resultsEl.textContent = `${visibleCount} certificate(s) found`;
            } else {
                resultsEl.textContent = '';
            }
        }
    }
};

// Confirmation dialogs
const Confirm = {
    delete(name) {
        return confirm(`Are you sure you want to delete the certificate "${name}"?\n\nThis will permanently delete both the certificate and private key files.`);
    },

    revoke(name) {
        return confirm(`Are you sure you want to revoke the certificate "${name}"?\n\nThis action cannot be undone. The certificate will no longer be valid.`);
    }
};

// PFX Export modal
const PFXExport = {
    show(certName) {
        const modal = document.getElementById('pfx-modal');
        const nameInput = document.getElementById('pfx-cert-name');

        if (modal && nameInput) {
            nameInput.value = certName;
            modal.classList.add('active');
            document.getElementById('pfx-password').focus();
        }
    },

    hide() {
        const modal = document.getElementById('pfx-modal');
        if (modal) {
            modal.classList.remove('active');
            document.getElementById('pfx-password').value = '';
            document.getElementById('pfx-password-confirm').value = '';
        }
    },

    submit() {
        const password = document.getElementById('pfx-password').value;
        const confirm = document.getElementById('pfx-password-confirm').value;
        const certName = document.getElementById('pfx-cert-name').value;

        if (password.length < 4) {
            alert('Password must be at least 4 characters long.');
            return false;
        }

        if (password !== confirm) {
            alert('Passwords do not match.');
            return false;
        }

        // Submit form
        const form = document.getElementById('pfx-form');
        if (form) {
            form.action = `export-pfx.php?name=${encodeURIComponent(certName)}`;
            form.submit();
            this.hide();
        }

        return true;
    }
};

// Certificate details modal
const CertDetails = {
    show(certName) {
        const modal = document.getElementById('details-modal');
        const content = document.getElementById('details-content');

        if (modal && content) {
            content.innerHTML = '<p>Loading...</p>';
            modal.classList.add('active');

            // Fetch certificate details
            fetch(`cert-details.php?name=${encodeURIComponent(certName)}&format=json`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        content.innerHTML = `<p class="error">${data.error}</p>`;
                    } else {
                        content.innerHTML = this.renderDetails(data);
                    }
                })
                .catch(error => {
                    content.innerHTML = `<p class="error">Error loading details: ${error.message}</p>`;
                });
        }
    },

    hide() {
        const modal = document.getElementById('details-modal');
        if (modal) {
            modal.classList.remove('active');
        }
    },

    renderDetails(data) {
        return `
            <dl class="details-list">
                <dt>Common Name (CN)</dt>
                <dd>${this.escape(data.cn)}</dd>

                <dt>Subject</dt>
                <dd>${this.escape(data.subject)}</dd>

                <dt>Issuer</dt>
                <dd>${this.escape(data.issuer)}</dd>

                <dt>Serial Number</dt>
                <dd>${this.escape(data.serial)}</dd>

                <dt>Valid From</dt>
                <dd>${this.escape(data.valid_from)}</dd>

                <dt>Valid To</dt>
                <dd>${this.escape(data.valid_to)}</dd>

                <dt>Subject Alternative Names</dt>
                <dd>${data.sans ? data.sans.map(s => this.escape(s)).join('<br>') : 'None'}</dd>

                <dt>Signature Algorithm</dt>
                <dd>${this.escape(data.signature_algorithm)}</dd>

                <dt>Public Key</dt>
                <dd>${this.escape(data.public_key)}</dd>

                <dt>Fingerprint (SHA-256)</dt>
                <dd style="font-family: monospace; font-size: 11px;">${this.escape(data.fingerprint)}</dd>
            </dl>
        `;
    },

    escape(str) {
        if (!str) return 'N/A';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    ThemeManager.init();
    SANManager.init();
    CertSearch.init();

    // Close modals on overlay click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
    });

    // Close modals on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                modal.classList.remove('active');
            });
        }
    });
});
