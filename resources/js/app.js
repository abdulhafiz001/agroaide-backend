import './bootstrap';
import Chart from 'chart.js/auto';

window.Chart = Chart;

// Global helpers for backward compatibility
window.openSidebar = function () {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    if (sidebar) sidebar.classList.remove('-translate-x-full');
    if (backdrop) backdrop.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
};

window.closeSidebar = function () {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    if (sidebar) sidebar.classList.add('-translate-x-full');
    if (backdrop) backdrop.classList.add('hidden');
    document.body.style.overflow = '';
};

window.togglePass = function (inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';

    if (btn) {
        const eyeOpen = btn.querySelector('.eye-open');
        const eyeClosed = btn.querySelector('.eye-closed');
        if (eyeOpen && eyeClosed) {
            eyeOpen.classList.toggle('hidden', isPassword);
            eyeClosed.classList.toggle('hidden', !isPassword);
        }
    }
};

// Event-delegated & attribute-driven interactions (CSP-compliant, zero inline onclick needed)
document.addEventListener('DOMContentLoaded', () => {
    // 1. Modals (open and close via data-modal-open / data-modal-close)
    document.querySelectorAll('[data-modal-open]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const modalId = btn.getAttribute('data-modal-open');
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const modalId = btn.getAttribute('data-modal-close');
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            }
        });
    });

    // Close modal on click outside the inner card
    document.querySelectorAll('[role="dialog"]').forEach((modal) => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal || e.target.classList.contains('modal-backdrop-area')) {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            }
        });
    });

    // Close modal & sidebar on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('[role="dialog"]:not(.hidden)').forEach((modal) => {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            });
            window.closeSidebar();
        }
    });

    // 2. Mobile sidebar
    const sidebarOpenBtn = document.getElementById('mobile-sidebar-open');
    const sidebarBackdrop = document.getElementById('sidebar-backdrop');
    if (sidebarOpenBtn) {
        sidebarOpenBtn.addEventListener('click', window.openSidebar);
    }
    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', window.closeSidebar);
    }

    // 3. Password visibility toggle
    document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const inputId = btn.getAttribute('data-toggle-password');
            window.togglePass(inputId, btn);
        });
    });
});
