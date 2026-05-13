/**
 * HomeCloud - Global app shell
 * Handles navigation, theme toggle, drag-drop, modals
 */

class HomeCloud {
  constructor() {
    this.currentRoute = 'login';
    this.theme = localStorage.getItem('hc-theme') || 'light';
    this.drawerOpen = false;
    this.init();
  }

  init() {
    this.applyTheme();
    this.setupNavigation();
    this.setupDragDrop();
    this.setupKeyboardShortcuts();
    this.setupModal();
    this.setupDrawer();
  }

  applyTheme() {
    document.documentElement.setAttribute('data-theme', this.theme);
    document.body.classList.toggle('dark', this.theme === 'dark');
  }

  toggleTheme() {
    this.theme = this.theme === 'light' ? 'dark' : 'light';
    localStorage.setItem('hc-theme', this.theme);
    this.applyTheme();
    document.querySelectorAll('[data-toggle-theme]').forEach(el => {
      const icon = el.querySelector('svg');
      if (icon) {
        const parent = icon.parentElement;
        parent.innerHTML = this.theme === 'dark' 
          ? window.Icons.sun().outerHTML 
          : window.Icons.moon().outerHTML;
      }
    });
  }

  goto(route) {
    // Close drawer on mobile
    this.closeDrawer();

    // Hide all pages
    document.querySelectorAll('[data-page]').forEach(el => {
      el.classList.add('hidden');
    });
    
    // Show target page
    const page = document.querySelector(`[data-page="${route}"]`);
    if (page) {
      page.classList.remove('hidden');
      this.currentRoute = route;
      
      // Update sidebar active
      document.querySelectorAll('[data-nav-item]').forEach(el => {
        el.classList.toggle('bg-white/20 border border-white/10', el.getAttribute('data-nav-item') === route);
      });

      // Update mobile tab bar active
      document.querySelectorAll('.tab-bar-item').forEach(el => {
        el.classList.toggle('active', el.dataset.tab === route);
      });
    }
  }

  toggleDrawer() {
    this.drawerOpen ? this.closeDrawer() : this.openDrawer();
  }

  openDrawer() {
    const sidebar = document.getElementById('sidebar');
    const drawer = sidebar?.querySelector('.drawer');
    const overlay = document.getElementById('drawer-overlay');
    if (drawer && overlay) {
      drawer.classList.add('open');
      overlay.classList.add('open');
      this.drawerOpen = true;
    }
  }

  closeDrawer() {
    const sidebar = document.getElementById('sidebar');
    const drawer = sidebar?.querySelector('.drawer');
    const overlay = document.getElementById('drawer-overlay');
    if (drawer && overlay) {
      drawer.classList.remove('open');
      overlay.classList.remove('open');
      this.drawerOpen = false;
    }
  }

  openModal(id, data = {}) {
    const modal = document.getElementById(id);
    if (modal) {
      modal.classList.remove('hidden');
      // Pass data if needed
      if (data.file) {
        modal.dataset.file = JSON.stringify(data.file);
      }
    }
  }

  closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.add('hidden');
  }

  setupNavigation() {
    document.querySelectorAll('[data-nav-item]').forEach(el => {
      el.addEventListener('click', () => {
        const route = el.getAttribute('data-nav-item');
        this.goto(route);
      });
    });

    // Theme toggle
    document.querySelectorAll('[data-toggle-theme]').forEach(el => {
      el.addEventListener('click', () => this.toggleTheme());
    });

    // Modal close buttons
    document.querySelectorAll('[data-modal-close]').forEach(el => {
      el.addEventListener('click', () => {
        const modal = el.closest('[data-modal]');
        if (modal) this.closeModal(modal.id);
      });
    });

    // Click outside modal to close
    document.querySelectorAll('[data-modal]').forEach(modal => {
      modal.addEventListener('click', (e) => {
        if (e.target === modal) this.closeModal(modal.id);
      });
    });
  }

  setupDragDrop() {
    let dragCounter = 0;
    const overlay = document.getElementById('drop-overlay');

    ['dragenter', 'dragover'].forEach(event => {
      document.addEventListener(event, (e) => {
        e.preventDefault();
        dragCounter++;
        if (overlay) overlay.classList.remove('hidden');
      });
    });

    document.addEventListener('dragleave', () => {
      dragCounter--;
      if (dragCounter === 0 && overlay) overlay.classList.add('hidden');
    });

    document.addEventListener('drop', (e) => {
      e.preventDefault();
      dragCounter = 0;
      if (overlay) overlay.classList.add('hidden');
      // TODO: handle file upload
    });
  }

  setupKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
      if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        // TODO: open command palette
      }
      if (e.key === 'Escape') {
        document.querySelectorAll('[data-modal]:not(.hidden)').forEach(m => {
          this.closeModal(m.id);
        });
      }
    });
  }

  setupModal() {
    // Delegate modal interactions
  }

  setupDrawer() {
    // Drawer toggle button (mobile menu button in topbar)
    document.querySelectorAll('[onclick*="toggleDrawer"]').forEach(el => {
      el.addEventListener('click', (e) => {
        e.stopPropagation();
        this.toggleDrawer();
      });
    });

    // Overlay click closes drawer
    const overlay = document.getElementById('drawer-overlay');
    if (overlay) {
      overlay.addEventListener('click', () => {
        this.closeDrawer();
      });
    }

    // Close drawer when nav item clicked (handled in goto())
    // Esc key closes drawer
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && this.drawerOpen) {
        this.closeDrawer();
      }
    });
  }
}

// Initialize
window.homecloud = new HomeCloud();
