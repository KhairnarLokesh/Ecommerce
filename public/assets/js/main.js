/**
 * Kalika Enterprises - Core Marketplace Client JavaScript
 * Flipkart & Amazon marketplace interaction patterns:
 * - Live cart badge synchronization
 * - Accessible account dropdown & mobile drawer
 * - Toast notification system
 * - Modal dialogs & quantity steppers
 */

document.addEventListener('DOMContentLoaded', () => {
  // --------------------------------------------------------------------------
  // 1. Account Menu Dropdown Controller (Desktop / Tablet)
  // --------------------------------------------------------------------------
  const accountTrigger = document.getElementById('accountMenuTrigger');
  const accountDropdown = document.getElementById('accountDropdownCard');

  if (accountTrigger && accountDropdown) {
    accountTrigger.addEventListener('click', (e) => {
      e.stopPropagation();
      const isExpanded = accountTrigger.getAttribute('aria-expanded') === 'true';
      if (isExpanded) {
        accountDropdown.classList.remove('show');
        accountTrigger.setAttribute('aria-expanded', 'false');
      } else {
        accountDropdown.classList.add('show');
        accountTrigger.setAttribute('aria-expanded', 'true');
      }
    });

    // Close when clicking anywhere outside
    document.addEventListener('click', (e) => {
      if (!accountTrigger.contains(e.target) && !accountDropdown.contains(e.target)) {
        accountDropdown.classList.remove('show');
        accountTrigger.setAttribute('aria-expanded', 'false');
      }
    });

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && accountDropdown.classList.contains('show')) {
        accountDropdown.classList.remove('show');
        accountTrigger.setAttribute('aria-expanded', 'false');
        accountTrigger.focus();
      }
    });
  }

  // --------------------------------------------------------------------------
  // 2. Mobile Slide-out Drawer Navigation
  // --------------------------------------------------------------------------
  const drawerOpenBtn = document.getElementById('mobileDrawerOpenBtn');
  const drawerCloseBtn = document.getElementById('mobileDrawerCloseBtn');
  const drawerOverlay = document.getElementById('mobileDrawerOverlay');
  const drawerPanel = document.getElementById('mobileDrawerPanel');

  const openDrawer = () => {
    if (drawerOverlay) {
      drawerOverlay.classList.add('open');
      drawerOverlay.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
      if (drawerOpenBtn) drawerOpenBtn.setAttribute('aria-expanded', 'true');
    }
  };

  const closeDrawer = () => {
    if (drawerOverlay) {
      drawerOverlay.classList.remove('open');
      drawerOverlay.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      if (drawerOpenBtn) drawerOpenBtn.setAttribute('aria-expanded', 'false');
    }
  };

  if (drawerOpenBtn) drawerOpenBtn.addEventListener('click', openDrawer);
  if (drawerCloseBtn) drawerCloseBtn.addEventListener('click', closeDrawer);

  if (drawerOverlay) {
    drawerOverlay.addEventListener('click', (e) => {
      if (e.target === drawerOverlay) closeDrawer();
    });
  }

  // --------------------------------------------------------------------------
  // 3. Global Toast Notification System
  // --------------------------------------------------------------------------
  const toastContainer = document.getElementById('toastContainer');

  window.showToast = function(message, actionText = null, actionUrl = null, duration = 4000) {
    if (!toastContainer) return;

    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.setAttribute('role', 'alert');

    let toastHtml = `<span>${message}</span>`;
    if (actionText && actionUrl) {
      toastHtml += `<a href="${actionUrl}" class="toast-action-btn">${actionText}</a>`;
    }
    toast.innerHTML = toastHtml;

    toastContainer.appendChild(toast);

    setTimeout(() => {
      toast.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(12px)';
      setTimeout(() => toast.remove(), 300);
    }, duration);
  };

  // --------------------------------------------------------------------------
  // 4. Live Cart Badge Synchronizer
  // --------------------------------------------------------------------------
  window.updateCartBadge = function(newCount) {
    const desktopBadge = document.getElementById('headerCartBadge');
    const bottomNavBadge = document.getElementById('bottomNavCartBadge');

    if (desktopBadge) {
      desktopBadge.textContent = newCount;
    }

    if (bottomNavBadge) {
      bottomNavBadge.textContent = newCount;
      bottomNavBadge.style.display = newCount > 0 ? 'flex' : 'none';
    }
  };

  // --------------------------------------------------------------------------
  // 5. Quantity Stepper Controller
  // --------------------------------------------------------------------------
  document.querySelectorAll('.qty-control').forEach((control) => {
    const input = control.querySelector('.qty-input');
    const minus = control.querySelector('[data-qty="minus"]');
    const plus = control.querySelector('[data-qty="plus"]');

    if (input && minus && plus) {
      minus.addEventListener('click', () => {
        let val = parseInt(input.value, 10) || 1;
        if (val > 1) {
          input.value = val - 1;
          input.dispatchEvent(new Event('change', { bubbles: true }));
        }
      });

      plus.addEventListener('click', () => {
        let val = parseInt(input.value, 10) || 1;
        if (val < 999) {
          input.value = val + 1;
          input.dispatchEvent(new Event('change', { bubbles: true }));
        }
      });
    }
  });

  // --------------------------------------------------------------------------
  // 6. Flash Alert Auto-Dismissal
  // --------------------------------------------------------------------------
  const flashContainer = document.querySelector('.flash-container');
  if (flashContainer) {
    const alerts = flashContainer.querySelectorAll('.alert');
    if (alerts.length > 0) {
      setTimeout(() => {
        alerts.forEach((alert) => {
          alert.style.transition = 'opacity 0.4s ease';
          alert.style.opacity = '0';
          setTimeout(() => alert.remove(), 400);
        });
      }, 5000);
    }
  }

  // --------------------------------------------------------------------------
  // 7. Modal Dialog Controller
  // --------------------------------------------------------------------------
  document.querySelectorAll('[data-modal-open]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const modalId = btn.getAttribute('data-modal-open');
      const target = document.getElementById(modalId);
      if (target) {
        target.classList.add('open');
        document.body.style.overflow = 'hidden';
      }
    });
  });

  document.querySelectorAll('[data-modal-close]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const modal = btn.closest('.modal-backdrop');
      if (modal) {
        modal.classList.remove('open');
        document.body.style.overflow = '';
      }
    });
  });

  document.querySelectorAll('.modal-backdrop').forEach((backdrop) => {
    backdrop.addEventListener('click', (e) => {
      if (e.target === backdrop) {
        backdrop.classList.remove('open');
        document.body.style.overflow = '';
      }
    });
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      const openModal = document.querySelector('.modal-backdrop.open');
      if (openModal) {
        openModal.classList.remove('open');
        document.body.style.overflow = '';
      }
    }
  });

  // --------------------------------------------------------------------------
  // 8. Landing Page Banner Carousel Controller
  // --------------------------------------------------------------------------
  const bannerCarousel = document.getElementById('bannerCarousel');
  const bannerTrack = document.getElementById('bannerTrack');
  const prevBtn = document.getElementById('bannerPrevBtn');
  const nextBtn = document.getElementById('bannerNextBtn');
  const dots = document.querySelectorAll('.banner-dot');

  if (bannerCarousel && bannerTrack && dots.length > 0) {
    let currentSlide = 0;
    const totalSlides = dots.length;
    let autoPlayTimer = null;

    const goToSlide = (index) => {
      currentSlide = (index + totalSlides) % totalSlides;
      bannerTrack.style.transform = `translateX(-${currentSlide * 100}%)`;
      dots.forEach((dot, idx) => {
        dot.classList.toggle('active', idx === currentSlide);
        dot.setAttribute('aria-current', idx === currentSlide ? 'true' : 'false');
      });
    };

    const nextSlide = () => goToSlide(currentSlide + 1);
    const prevSlide = () => goToSlide(currentSlide - 1);

    if (nextBtn) nextBtn.addEventListener('click', () => { nextSlide(); resetAutoplay(); });
    if (prevBtn) prevBtn.addEventListener('click', () => { prevSlide(); resetAutoplay(); });

    dots.forEach((dot) => {
      dot.addEventListener('click', () => {
        const slideIdx = parseInt(dot.getAttribute('data-slide'), 10);
        if (!isNaN(slideIdx)) {
          goToSlide(slideIdx);
          resetAutoplay();
        }
      });
    });

    // Touch swipe support
    let touchStartX = 0;
    let touchEndX = 0;
    bannerCarousel.addEventListener('touchstart', (e) => {
      touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });

    bannerCarousel.addEventListener('touchend', (e) => {
      touchEndX = e.changedTouches[0].screenX;
      if (touchStartX - touchEndX > 40) {
        nextSlide();
        resetAutoplay();
      } else if (touchEndX - touchStartX > 40) {
        prevSlide();
        resetAutoplay();
      }
    }, { passive: true });

    // Autoplay (5s) with pause on hover
    const startAutoplay = () => {
      if (!autoPlayTimer) {
        autoPlayTimer = setInterval(nextSlide, 5000);
      }
    };

    const stopAutoplay = () => {
      if (autoPlayTimer) {
        clearInterval(autoPlayTimer);
        autoPlayTimer = null;
      }
    };

    const resetAutoplay = () => {
      stopAutoplay();
      startAutoplay();
    };

    bannerCarousel.addEventListener('mouseenter', stopAutoplay);
    bannerCarousel.addEventListener('mouseleave', startAutoplay);

    startAutoplay();
  }

  // --------------------------------------------------------------------------
  // 9. AJAX Add to Cart Form Handler
  // --------------------------------------------------------------------------
  document.addEventListener('submit', (e) => {
    const form = e.target.closest('.add-to-cart-form');
    if (!form) return;

    e.preventDefault();
    const btn = form.querySelector('button[type="submit"]') || form.querySelector('.add-cart-btn') || form.querySelector('.btn-add-cart');
    const originalContent = btn ? btn.innerHTML : '';
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span style="font-weight:600;">Adding...</span>';
    }

    const formData = new FormData(form);
    formData.append('ajax', '1');

    fetch(form.action, {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    })
    .then(async (res) => {
      if (!res.ok && res.status !== 403) {
        throw new Error('HTTP ' + res.status);
      }
      return res.json();
    })
    .then((data) => {
      if (data && data.success) {
        if (btn) {
          btn.innerHTML = '<span style="font-weight:700; color:#15803d;">✓ Added</span>';
          setTimeout(() => {
            btn.disabled = false;
            btn.innerHTML = originalContent;
          }, 1500);
        }
        if (data.cart && typeof data.cart.item_count !== 'undefined') {
          window.updateCartBadge(data.cart.item_count);
        }
        const cartUrl = form.action.replace(/\/products\.php.*$/, '/cart.php');
        window.showToast(data.message || 'Item added to wholesale cart!', 'View Cart', cartUrl);
      } else {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = originalContent;
        }
        window.showToast((data && data.message) ? data.message : 'Could not add item to cart.', null, null, 4000);
      }
    })
    .catch((err) => {
      console.warn('AJAX cart add fallback to standard submit:', err);
      // Fallback: submit standard form if AJAX or JSON parser fails
      form.submit();
    });
  });

  // --------------------------------------------------------------------------
  // 7. Password Visibility Toggle (Show / Hide Password)
  // --------------------------------------------------------------------------
  document.addEventListener('click', (e) => {
    const toggleBtn = e.target.closest('.btn-toggle-password');
    if (!toggleBtn) return;
    e.preventDefault();
    const targetId = toggleBtn.getAttribute('data-target');
    const input = document.getElementById(targetId);
    if (!input) return;

    const isPassword = input.getAttribute('type') === 'password';
    input.setAttribute('type', isPassword ? 'text' : 'password');

    const eyeOpen = toggleBtn.querySelector('.eye-open');
    const eyeClosed = toggleBtn.querySelector('.eye-closed');
    if (eyeOpen && eyeClosed) {
      if (isPassword) {
        eyeOpen.style.display = 'none';
        eyeClosed.style.display = 'inline';
      } else {
        eyeOpen.style.display = 'inline';
        eyeClosed.style.display = 'none';
      }
    }
  });

  // --------------------------------------------------------------------------
  // 8. Single Submission Guard (Prevent Double Clicks on Auth Forms)
  // --------------------------------------------------------------------------
  document.querySelectorAll('form').forEach((f) => {
    f.addEventListener('submit', function() {
      const submitBtn = f.querySelector('button[type="submit"]');
      if (submitBtn && !f.hasAttribute('data-submitting') && f.checkValidity()) {
        f.setAttribute('data-submitting', 'true');
        setTimeout(() => {
          submitBtn.disabled = true;
          submitBtn.style.opacity = '0.7';
          submitBtn.style.pointerEvents = 'none';
        }, 50);
      }
    });
  });
});

