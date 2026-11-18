/**
 * Kopii Marketplace — Client-Side Progressive Enhancement
 * Built on native Popover API, <dialog>.showModal(), focusgroup tablists,
 * hidden="until-found", <progress>, ResizeObserver, and :user-invalid sync.
 */
(() => {
  'use strict';

  // 1. No third-party polyfills. This site used to import `@oddbird/popover-polyfill@latest` from unpkg
  //    and an unversioned focusgroup polyfill from esm.sh on browsers lacking those features, which
  //    ran unpinned code from other servers on every visitor. Both features are progressive
  //    enhancements: without native popover support the menu simply stays an ordinary visible list,
  //    and without focusgroup support the tab lists use normal Tab / click navigation.

  // 2. Responsive Site Navigation: Measure inline fit via ResizeObserver (navbar guide)
  const initNavbar = () => {
    const header = document.querySelector('.site-header');
    const inner = header?.querySelector('.header-inner');
    const menu = document.getElementById('site-menu');
    if (!header || !inner || !menu) return;
    // Without native popover support the menu stays a plain list (there is no polyfill any more).
    if (!('popover' in HTMLElement.prototype)) return;

    let lastCollapsedWidth = 0;

    const syncLayout = (forceMeasure = false) => {
      const currentlyWide = header.classList.contains('is-wide');
      const currentWidth = inner.clientWidth;

      if (!currentlyWide && !forceMeasure && currentWidth <= lastCollapsedWidth) {
        return;
      }

      const wasOpen = !currentlyWide && menu.matches(':popover-open, .\\:popover-open');

      header.classList.add('is-measuring', 'is-wide');
      menu.removeAttribute('popover');

      const fitsInline = inner.scrollWidth <= inner.clientWidth;

      if (!fitsInline) {
        header.classList.remove('is-wide');
        menu.setAttribute('popover', 'auto');
        lastCollapsedWidth = currentWidth;
        if (wasOpen && typeof menu.showPopover === 'function') {
          try { menu.showPopover(); } catch {}
        }
      } else {
        lastCollapsedWidth = 0;
      }

      requestAnimationFrame(() => {
        header.classList.remove('is-measuring');
      });
    };

    syncLayout(true);

    if ('ResizeObserver' in window) {
      const ro = new ResizeObserver(() => syncLayout(false));
      ro.observe(header);
      ro.observe(menu);
    } else {
      window.addEventListener('resize', () => syncLayout(true));
    }
  };

  // 3. Hero: Intent Pill Switcher + Continuous Looping Carousel (`carousel` & `scrollability-affordance-hints`)
  const initHeroSpotlight = () => {
    const hero = document.querySelector('[data-home-hero]');
    if (!hero) return;

    // A. Top "I'm looking for [Talent | Studios & Services]" Intent Switcher
    const intentBtns = [...hero.querySelectorAll('[data-intent-mode]')];
    const headingEl = hero.querySelector('[data-hero-heading]');
    const leadEl = hero.querySelector('[data-hero-lead]');
    const ctaEl = hero.querySelector('[data-hero-cta]');

    const renderHeading = (mode) => {
      if (!headingEl) return;
      headingEl.textContent = '';
      if (mode === 'studios') {
        headingEl.append('Hire ');
        const u = document.createElement('span');
        u.className = 'hero-underline';
        u.textContent = 'Local Studios';
        headingEl.append(u, ' &', document.createElement('br'), 'Service Teams');
      } else {
        headingEl.append('Find the ');
        const u = document.createElement('span');
        u.className = 'hero-underline';
        u.textContent = 'Right Talent';
        headingEl.append(u, document.createElement('br'), 'Across Uganda');
      }
    };

    intentBtns.forEach((btn) => {
      btn.addEventListener('click', () => {
        const mode = btn.getAttribute('data-intent-mode') || 'talent';
        intentBtns.forEach((b) => b.setAttribute('aria-pressed', b === btn ? 'true' : 'false'));
        renderHeading(mode);
        if (leadEl) {
          leadEl.textContent = mode === 'studios'
            ? 'Find photography houses, wedding & summit caterers, bespoke fashion ateliers, and digital agencies across Uganda — with upfront UGX packages and direct contact.'
            : 'Kopii is a free showcase marketplace for Ugandan photographers, brand designers, bespoke tailors, event producers, software developers, and other creatives. Browse real portfolios, compare itemized UGX packages, and hire directly with zero commission.';
        }
        if (ctaEl) {
          ctaEl.textContent = btn.getAttribute('data-intent-cta-label') || 'Hire Top Talent';
          ctaEl.href = btn.getAttribute('data-intent-cta-href') || '/hire';
        }
      });
    });

    // B. Hero Cutout Portrait + Chamfered Dotted-Map Card + Carousel
    const carousel = hero.querySelector('[data-hero-carousel]');
    const track = carousel?.querySelector('.hero-carousel-track');
    const slides = track ? [...track.querySelectorAll('.hero-talent-slide')] : [];
    if (!carousel || !track || !slides.length) return;

    const portraitEl = hero.querySelector('[data-hero-portrait]');
    const mapEl = hero.querySelector('[data-hero-map]');
    const nameEl = hero.querySelector('[data-hero-name]');
    const verticalEl = hero.querySelector('[data-hero-vertical]');
    const verifiedEl = hero.querySelector('[data-hero-verified]');
    const roleEl = hero.querySelector('[data-hero-role]');
    const clientEl = hero.querySelector('[data-hero-client]');
    const clientLogoEl = hero.querySelector('[data-hero-client-logo]');

    const prevBtn = carousel.querySelector('[data-direction="previous"]');
    const nextBtn = carousel.querySelector('[data-direction="next"]');
    const markersNav = carousel.querySelector('[data-carousel-markers]');
    const statusEl = carousel.querySelector('[data-carousel-status]');
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const canLoop = slides.length >= 3;

    // Fallback for sibling-count() per `carousel` guide
    if (!CSS.supports('order: sibling-count()')) {
      track.style.setProperty('--children-count', String(slides.length));
    }

    // Build direct marker buttons from each slide's data-marker-name
    const markerBtns = slides.map((slide, i) => {
      const mBtn = document.createElement('button');
      mBtn.type = 'button';
      mBtn.setAttribute('aria-label', slide.getAttribute('data-marker-name') || `Go to slide ${i + 1}`);
      mBtn.setAttribute('aria-current', 'false');
      markersNav?.append(mBtn);
      return mBtn;
    });

    const syncStageFromSlide = (slide) => {
      if (!slide) return;
      const idx = Number(slide.getAttribute('data-slide-index') || 0);

      slides.forEach((s, i) => {
        const cardBtn = s.querySelector('.hero-slide-btn');
        cardBtn?.setAttribute('aria-current', i === idx ? 'true' : 'false');
      });
      markerBtns.forEach((m, i) => {
        m.setAttribute('aria-current', i === idx ? 'true' : 'false');
      });

      const person = slide.getAttribute('data-person') || '';
      const url = slide.getAttribute('data-url') || '#';
      const portrait = slide.getAttribute('data-portrait') || '';
      const vertical = slide.getAttribute('data-vertical') || '';
      const role = slide.getAttribute('data-role') || '';
      const client = slide.getAttribute('data-client') || '';
      const clientLogo = slide.getAttribute('data-client-logo') || '';
      const clientW = slide.getAttribute('data-client-w') || '240';
      const clientH = slide.getAttribute('data-client-h') || '64';
      const city = slide.getAttribute('data-city') || '';
      const pinX = slide.getAttribute('data-pin-x') || '57%';
      const pinY = slide.getAttribute('data-pin-y') || '57%';

      if (portraitEl && portrait && portraitEl.getAttribute('src') !== portrait) {
        portraitEl.src = portrait;
        portraitEl.alt = `${person}, ${role} in ${city}, Uganda`;
      }
      if (nameEl) {
        nameEl.textContent = person;
        nameEl.href = url;
      }
      if (verticalEl) verticalEl.textContent = vertical;
      if (verifiedEl) verifiedEl.style.display = slide.getAttribute('data-verified') === '1' ? '' : 'none';
      if (roleEl) roleEl.textContent = role;
      if (clientLogoEl) {
        if (clientLogo) {
          clientLogoEl.src = clientLogo;
          clientLogoEl.alt = client;
          clientLogoEl.width = Number(clientW);
          clientLogoEl.height = Number(clientH);
          clientLogoEl.hidden = false;
          if (clientEl) clientEl.hidden = true;
        } else {
          clientLogoEl.hidden = true;
          if (clientEl) {
            clientEl.textContent = client;
            clientEl.hidden = false;
          }
        }
      } else if (clientEl) {
        clientEl.textContent = client;
      }
      if (mapEl) {
        mapEl.style.setProperty('--pin-x', pinX);
        mapEl.style.setProperty('--pin-y', pinY);
        const pinDot = mapEl.querySelector('.hero-map-pin');
        if (pinDot) pinDot.title = `${city}, Uganda`;
      }
    };

    const slideCenterOffset = (slide) => {
      const trackRect = track.getBoundingClientRect();
      const slideRect = slide.getBoundingClientRect();
      return (slideRect.left + slideRect.width / 2) - (trackRect.left + trackRect.width / 2);
    };

    const getVisualSlides = () =>
      [...slides].sort((a, b) => a.getBoundingClientRect().left - b.getBoundingClientRect().left);

    const findNearestSlide = () => {
      let best = slides[0];
      let bestDist = Infinity;
      for (const s of slides) {
        const d = Math.abs(slideCenterOffset(s));
        if (d < bestDist) {
          bestDist = d;
          best = s;
        }
      }
      return best;
    };

    let settledSlide = null;
    let liveSlide = null;
    let pendingTarget = null;
    let pendingOriginScroll = null;
    let queuedDirection = null;
    let rafId = 0;

    const applyParking = (targetIdx, lockOverflow = true) => {
      if (!canLoop) return;
      const anchor = slides[targetIdx] || findNearestSlide();
      const beforeLeft = anchor.getBoundingClientRect().left;
      const prevCurrent = track.style.getPropertyValue('--current');
      const nextCurrent = targetIdx === null ? 'initial' : String(targetIdx);
      if (prevCurrent === nextCurrent) return;

      track.style.scrollSnapType = 'none';
      if (lockOverflow) track.style.overflowX = 'hidden';
      track.style.setProperty('--current', nextCurrent);

      const afterLeft = anchor.getBoundingClientRect().left;
      const delta = afterLeft - beforeLeft;
      if (Math.abs(delta) > 0.5) {
        track.scrollBy({ left: delta, behavior: 'instant' });
      }
      track.style.scrollSnapType = '';

      if (lockOverflow) {
        requestAnimationFrame(() => {
          requestAnimationFrame(() => {
            track.style.overflowX = '';
          });
        });
      }
    };

    const scrollToSlide = (targetSlide, smooth = true) => {
      if (!targetSlide) return;
      pendingTarget = targetSlide;
      pendingOriginScroll = track.scrollLeft;
      liveSlide = targetSlide;
      syncStageFromSlide(targetSlide);

      const offset = slideCenterOffset(targetSlide);
      const useSmooth = smooth && !prefersReducedMotion.matches;
      if (Math.abs(offset) < 1) {
        pendingTarget = null;
        onSettle(false);
        return;
      }
      track.scrollBy({ left: offset, behavior: useSmooth ? 'smooth' : 'instant' });
      if (!useSmooth) {
        pendingTarget = null;
        onSettle(true);
      }
    };

    const stepDirection = (dir) => {
      // If slides are temporarily unparked for a marker trip, queue direction until settle
      if (canLoop && track.style.getPropertyValue('--current') === 'initial' && pendingTarget) {
        queuedDirection = dir;
        return;
      }
      const baseSlide = pendingTarget || findNearestSlide();
      const visual = getVisualSlides();
      const pos = visual.indexOf(baseSlide);
      if (pos === -1) return;
      const nextPos = pos + dir;
      if (nextPos >= 0 && nextPos < visual.length) {
        scrollToSlide(visual[nextPos], true);
      } else if (canLoop) {
        const wrapped = visual[(nextPos + visual.length) % visual.length];
        scrollToSlide(wrapped, true);
      }
    };

    const onSettle = (announce = true) => {
      if (pendingTarget && Math.abs(track.scrollLeft - (pendingOriginScroll ?? -9999)) < 1) {
        return;
      }
      pendingTarget = null;
      pendingOriginScroll = null;

      const current = findNearestSlide();
      const idx = Number(current.getAttribute('data-slide-index') || 0);
      liveSlide = current;
      syncStageFromSlide(current);

      const replayDir = queuedDirection;
      queuedDirection = null;
      applyParking(idx, replayDir === null);

      if (announce && settledSlide && settledSlide !== current && statusEl) {
        const person = current.getAttribute('data-person') || '';
        statusEl.textContent = `Slide ${idx + 1} of ${slides.length}: ${person}`;
      }
      settledSlide = current;

      if (replayDir !== null) {
        stepDirection(replayDir);
      }
    };

    // Live rAF scroll sync (skipped while a scripted scroll has a pendingTarget)
    track.addEventListener('scroll', () => {
      if (pendingTarget || rafId) return;
      rafId = requestAnimationFrame(() => {
        rafId = 0;
        if (pendingTarget) return;
        const nearest = findNearestSlide();
        if (nearest !== liveSlide) {
          liveSlide = nearest;
          syncStageFromSlide(nearest);
        }
      });
    }, { passive: true });

    if ('onscrollend' in window) {
      track.addEventListener('scrollend', () => onSettle(true));
    } else {
      let scrollTimer;
      track.addEventListener('scroll', () => {
        clearTimeout(scrollTimer);
        scrollTimer = setTimeout(() => onSettle(true), 140);
      }, { passive: true });
    }

    prevBtn?.addEventListener('click', () => stepDirection(-1));
    nextBtn?.addEventListener('click', () => stepDirection(1));

    // Clicking any slide card or marker navigates in sequence order
    const goToMarkerIndex = (i) => {
      const target = slides[i];
      if (!target) return;
      if (target === (pendingTarget || findNearestSlide())) {
        syncStageFromSlide(target);
        return;
      }
      if (canLoop) {
        applyParking(null, false);
      }
      scrollToSlide(target, true);
    };

    slides.forEach((slide, i) => {
      slide.querySelector('.hero-slide-btn')?.addEventListener('click', () => goToMarkerIndex(i));
    });
    markerBtns.forEach((mBtn, i) => {
      mBtn.addEventListener('click', () => goToMarkerIndex(i));
    });

    // Edge-fade fallback when scroll-driven animations are unsupported (`scrollability-affordance-hints`)
    if (!CSS.supports('animation-timeline', 'scroll()') && 'IntersectionObserver' in window) {
      const first = track.firstElementChild;
      const last = track.lastElementChild;
      if (first && last) {
        const io = new IntersectionObserver(
          (entries) => {
            for (const { target, intersectionRatio } of entries) {
              track.classList.toggle(target === first ? 'more-start' : 'more-end', intersectionRatio < 1);
            }
          },
          { root: track, threshold: 1, rootMargin: '1px' }
        );
        io.observe(first);
        io.observe(last);
      }
    }

    // Initial silent center & park on load (leaving polite status region empty)
    const initIdx = Math.min(slides.length - 1, Math.max(0, Number(carousel.getAttribute('data-initial-index') || 0)));
    const initSlide = slides[initIdx] || slides[0];
    track.style.scrollSnapType = 'none';
    const initOffset = slideCenterOffset(initSlide);
    if (Math.abs(initOffset) > 0.5) {
      track.scrollBy({ left: initOffset, behavior: 'instant' });
    }
    track.style.setProperty('--current', String(initIdx));
    track.style.scrollSnapType = '';
    settledSlide = initSlide;
    liveSlide = initSlide;
    syncStageFromSlide(initSlide);
  };

  // 4. Verticals Tabbed Showcase (tabs guide: focusgroup="tablist nomemory" + hidden="until-found")
  const initVerticalTabs = () => {
    document.querySelectorAll('[data-vertical-tabs]').forEach((shell) => {
      const tablist = shell.querySelector('.vertical-tablist');
      const tabs = tablist ? [...tablist.querySelectorAll('.vertical-tab')] : [];
      const panels = [...shell.querySelectorAll('[role="tabpanel"]')];
      if (!tablist || !tabs.length || !panels.length) return;

      const selectTab = (selectedTab) => {
        if (!selectedTab) return;
        const activePanelId = selectedTab.getAttribute('data-panel-id');

        for (const tab of tabs) {
          const isSel = tab === selectedTab;
          tab.setAttribute('aria-selected', isSel ? 'true' : 'false');
          tab.toggleAttribute('focusgroupstart', isSel);
        }

        for (const panel of panels) {
          if (panel.id === activePanelId) {
            panel.hidden = false;
          } else {
            panel.hidden = 'until-found';
          }
        }

        selectedTab.scrollIntoView({
          block: 'nearest',
          inline: 'nearest',
          container: 'nearest',
        });
      };

      // Selection follows focus + pointer click
      tabs.forEach((tab) => {
        tab.addEventListener('click', () => selectTab(tab));
        tab.addEventListener('focus', () => selectTab(tab));
      });

      // Offline keyboard fallback if native focusgroup & CDN polyfill are both absent
      if (!Object.hasOwn(HTMLElement.prototype, 'focusGroup')) {
        tablist.addEventListener('keydown', (e) => {
          if (tablist.getAttribute('role') === 'tablist') return; // polyfill active
          const idx = tabs.indexOf(document.activeElement);
          if (idx === -1) return;
          let nextIdx = -1;
          if (e.key === 'ArrowRight') nextIdx = (idx + 1) % tabs.length;
          else if (e.key === 'ArrowLeft') nextIdx = (idx - 1 + tabs.length) % tabs.length;
          else if (e.key === 'Home') nextIdx = 0;
          else if (e.key === 'End') nextIdx = tabs.length - 1;
          if (nextIdx !== -1) {
            e.preventDefault();
            tabs[nextIdx].focus();
          }
        });
      }

      // Sync tab selection when browser find-in-page reveals a hidden="until-found" panel
      panels.forEach((panel) => {
        panel.addEventListener('beforematch', () => {
          const ownerId = panel.getAttribute('aria-labelledby');
          const matchingTab = ownerId ? document.getElementById(ownerId) : null;
          if (matchingTab) selectTab(matchingTab);
        });
      });

      // IntersectionObserver edge-fade fallback when scroll-driven animations are unsupported
      if (!CSS.supports('animation-timeline', 'scroll()') && 'IntersectionObserver' in window) {
        const first = tablist.firstElementChild;
        const last = tablist.lastElementChild;
        if (first && last) {
          const io = new IntersectionObserver(
            (entries) => {
              for (const { target, intersectionRatio } of entries) {
                tablist.classList.toggle(target === first ? 'more-start' : 'more-end', intersectionRatio < 1);
              }
            },
            { root: tablist, threshold: 1, rootMargin: '1px' }
          );
          io.observe(first);
          io.observe(last);
        }
      }
    });
  };

  // 5. Guided Hiring Wizard Step Progression (/hire — updates <progress> + polite status without stealing focus onto <progress>)
  const initHireWizard = () => {
    const form = document.querySelector('[data-hire-wizard]');
    if (!form) return;

    const step1 = form.querySelector('[data-wizard-step="1"]');
    const step2 = form.querySelector('[data-wizard-step="2"]');
    const nextBtn = form.querySelector('[data-wizard-next]');
    const prevBtn = form.querySelector('[data-wizard-prev]');
    const progressEl = document.getElementById('hire-progress');
    const statusEl = document.getElementById('wizard-step-status');
    const step2Heading = document.getElementById('wizard-step2-heading');
    if (!step1 || !step2) return;

    nextBtn?.addEventListener('click', () => {
      step1.hidden = true;
      step2.hidden = false;
      if (progressEl) progressEl.value = 100;
      if (statusEl) statusEl.textContent = 'Step 2 of 2: Location, Timeline & UGX Budget';
      step2Heading?.focus();
    });

    prevBtn?.addEventListener('click', () => {
      step2.hidden = true;
      step1.hidden = false;
      if (progressEl) progressEl.value = 50;
      if (statusEl) statusEl.textContent = 'Step 1 of 2: Select Discipline & Specialist Type';
      const checkedRadio = step1.querySelector('input[type="radio"]:checked') || step1.querySelector('input');
      checkedRadio?.focus();
    });
  };

  // 6. Modal <dialog> Light-Dismiss Fallback (for browsers without native `closedby="any"`)
  const initDialogs = () => {
    const dialogs = document.querySelectorAll('dialog.modal-box');

    dialogs.forEach((dialog) => {
      dialog.addEventListener('close', () => {
        dialog.returnValue = '';
      });

      if (!('closedBy' in HTMLDialogElement.prototype) && dialog.getAttribute('closedby') === 'any') {
        const outside = (e) => {
          const r = dialog.getBoundingClientRect();
          return e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom;
        };
        let pressedOutside = false;
        dialog.addEventListener('pointerdown', (e) => {
          pressedOutside = e.target === dialog && outside(e);
        });
        dialog.addEventListener('pointerup', (e) => {
          if (pressedOutside && e.target === dialog && outside(e)) {
            dialog.close();
          }
          pressedOutside = false;
        });
      }
    });

    document.querySelectorAll('[data-open-modal]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-open-modal');
        const dialog = id ? document.getElementById(id) : null;
        if (!dialog || typeof dialog.showModal !== 'function') return;

        const pkgId = btn.getAttribute('data-package-id');
        const pkgPrice = btn.getAttribute('data-package-price');
        const workTitle = btn.getAttribute('data-work-title');

        const pkgSelect = dialog.querySelector('select[name="package_id"]');
        if (pkgSelect && pkgId) {
          pkgSelect.value = pkgId;
        }
        const budgetInput = dialog.querySelector('input[name="budget_ugx"]');
        if (budgetInput && pkgPrice && !budgetInput.value) {
          budgetInput.value = pkgPrice;
        }
        const detailsBox = dialog.querySelector('textarea[name="project_details"]');
        if (detailsBox && workTitle && !detailsBox.value.includes(workTitle)) {
          detailsBox.value = `Hi! I loved "${workTitle}" in your portfolio and would like a quote for something similar. `;
        }

        dialog.showModal();
      });
    });

    document.querySelectorAll('[data-close-modal]').forEach((btn) => {
      btn.addEventListener('click', () => {
        btn.closest('dialog')?.close();
      });
    });
  };

  // 7. Portfolio Showcase Lightbox Viewer (<dialog closedby="any">)
  const initPortfolioLightbox = () => {
    const modal = document.getElementById('portfolio-lightbox');
    if (!modal) return;

    const triggers = [...document.querySelectorAll('[data-lightbox-src]')];
    if (!triggers.length) return;

    const imgEl = modal.querySelector('[data-lb-img]');
    const titleEl = modal.querySelector('[data-lb-title]');
    const tagEl = modal.querySelector('[data-lb-tag]');
    const descEl = modal.querySelector('[data-lb-desc]');
    const creatorLink = modal.querySelector('[data-lb-creator]');
    const locEl = modal.querySelector('[data-lb-location]');
    const prevBtn = modal.querySelector('[data-lb-prev]');
    const nextBtn = modal.querySelector('[data-lb-next]');
    const quoteBtn = modal.querySelector('[data-lb-quote]');

    let currentIndex = 0;

    const renderSlide = (idx) => {
      currentIndex = (idx + triggers.length) % triggers.length;
      const btn = triggers[currentIndex];
      if (!btn) return;

      if (imgEl) {
        const nextSrc = btn.getAttribute('data-lightbox-src') || '';
        imgEl.alt = btn.getAttribute('data-lightbox-title') || 'Portfolio showcase photo';
        if (imgEl.getAttribute('src') !== nextSrc) {
          // Dim the previous photo until the new one has loaded (full-size files can be slow on mobile data).
          imgEl.classList.add('is-loading');
          imgEl.onload = imgEl.onerror = () => imgEl.classList.remove('is-loading');
          imgEl.src = nextSrc;
        }
      }
      if (titleEl) titleEl.textContent = btn.getAttribute('data-lightbox-title') || '';
      if (tagEl) {
        const tag = btn.getAttribute('data-lightbox-tag') || '';
        tagEl.textContent = tag;
        tagEl.hidden = !tag;
      }
      if (descEl) descEl.textContent = btn.getAttribute('data-lightbox-desc') || '';
      if (locEl) {
        const loc = btn.getAttribute('data-lightbox-loc') || '';
        locEl.textContent = loc ? `Shot / Delivered in ${loc}` : '';
        locEl.hidden = !loc;
      }
      if (creatorLink) {
        const cName = btn.getAttribute('data-lightbox-creator') || '';
        const cHref = btn.getAttribute('data-lightbox-creator-url') || '#';
        creatorLink.textContent = cName ? `View ${cName} Dossier` : 'View Talent Dossier';
        creatorLink.href = cHref;
        creatorLink.hidden = !cName;
      }
      if (quoteBtn) {
        quoteBtn.setAttribute('data-work-title', btn.getAttribute('data-lightbox-title') || '');
      }
    };

    triggers.forEach((btn, idx) => {
      btn.addEventListener('click', () => {
        renderSlide(idx);
        modal.showModal();
      });
    });

    prevBtn?.addEventListener('click', () => renderSlide(currentIndex - 1));
    nextBtn?.addEventListener('click', () => renderSlide(currentIndex + 1));

    modal.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowLeft') renderSlide(currentIndex - 1);
      if (e.key === 'ArrowRight') renderSlide(currentIndex + 1);
    });

    quoteBtn?.addEventListener('click', () => {
      const workTitle = quoteBtn.getAttribute('data-work-title') || '';
      modal.close();
      const quoteDialog = document.getElementById('quote-modal');
      if (quoteDialog && typeof quoteDialog.showModal === 'function') {
        const detailsBox = quoteDialog.querySelector('textarea[name="project_details"]');
        if (detailsBox && workTitle) {
          detailsBox.value = `Hi! I saw "${workTitle}" in your Kopii dossier and would love to request a quote for a similar shoot/project. `;
        }
        quoteDialog.showModal();
      }
    });
  };

  // 8. Accessible Show/Hide Password Toggle & Registration Role Switcher
  const initAuthEnhancements = () => {
    document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const targetId = btn.getAttribute('data-toggle-password');
        const input = targetId ? document.getElementById(targetId) : null;
        if (!input) return;
        const showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        btn.setAttribute('aria-pressed', showing ? 'false' : 'true');
        btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
      });
    });

    const roleRadios = document.querySelectorAll('input[name="account_role"]');
    const providerSection = document.getElementById('provider-onboarding-fields');
    if (roleRadios.length && providerSection) {
      const syncRoleFields = () => {
        const selected = document.querySelector('input[name="account_role"]:checked')?.value || 'provider';
        const isProvider = selected === 'provider';
        providerSection.hidden = !isProvider;
        providerSection.inert = !isProvider;
        providerSection.querySelectorAll('[data-provider-required]').forEach((el) => {
          el.required = isProvider;
        });
      };
      roleRadios.forEach((r) => r.addEventListener('change', syncRoleFields));
      syncRoleFields();
    }

    document.querySelectorAll('[data-demo-email]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const emailField = document.getElementById('login-email');
        const passField = document.getElementById('login-password');
        if (emailField) emailField.value = btn.getAttribute('data-demo-email') || '';
        if (passField) passField.value = btn.getAttribute('data-demo-pass') || '';
        emailField?.focus();
      });
    });
  };

  // 9. Provider Shortlist / Compare Tray (LocalStorage powered)
  const initShortlist = () => {
    const STORAGE_KEY = 'kopii_shortlist_v1';
    const readIds = () => {
      try {
        const parsed = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
        return Array.isArray(parsed) ? parsed.map(Number).filter(Boolean) : [];
      } catch {
        return [];
      }
    };
    const writeIds = (ids) => {
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify([...new Set(ids)]));
      } catch {}
    };

    const tray = document.getElementById('shortlist-tray');
    const countEl = document.getElementById('shortlist-count');
    const compareLink = document.getElementById('shortlist-compare-link');
    const clearBtn = document.getElementById('shortlist-clear-btn');

    const refreshUI = () => {
      const ids = readIds();
      document.querySelectorAll('.shortlist-btn[data-provider-id]').forEach((btn) => {
        const pid = Number(btn.getAttribute('data-provider-id'));
        const saved = ids.includes(pid);
        btn.setAttribute('aria-pressed', saved ? 'true' : 'false');
        const label = btn.querySelector('[data-shortlist-label]');
        if (label) label.textContent = saved ? 'Saved' : 'Compare';
      });

      if (tray && countEl && compareLink) {
        tray.hidden = ids.length === 0;
        countEl.textContent = String(ids.length);
        const baseExplore = compareLink.getAttribute('data-explore-url') || '/explore';
        compareLink.href = `${baseExplore}?shortlist=${ids.join(',')}`;
      }
    };

    document.querySelectorAll('.shortlist-btn[data-provider-id]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const pid = Number(btn.getAttribute('data-provider-id'));
        if (!pid) return;
        const ids = readIds();
        const next = ids.includes(pid) ? ids.filter((x) => x !== pid) : [...ids, pid];
        writeIds(next);
        refreshUI();
      });
    });

    clearBtn?.addEventListener('click', () => {
      writeIds([]);
      refreshUI();
    });

    refreshUI();
  };

  // 10. Sync :user-invalid with ARIA (`aria-invalid` + `aria-describedby` + polite live announcer)
  const initDeferredValidation = () => {
    const announcer = document.createElement('div');
    announcer.setAttribute('aria-live', 'polite');
    announcer.className = 'visually-hidden';
    document.body.append(announcer);

    let announceTimer;
    let announcedField;
    const dirtyState = new WeakMap();
    const flagged = new WeakSet();
    const announced = new WeakSet();
    const supportsUserInvalid = CSS.supports('selector(:user-invalid)');

    const linkError = (el, shown) => {
      const errorId = el.dataset.error;
      if (!errorId) return;
      const ids = (el.getAttribute('aria-describedby') || '')
        .split(/\s+/)
        .filter((id) => id && id !== errorId);
      if (shown) ids.push(errorId);
      if (ids.length) el.setAttribute('aria-describedby', ids.join(' '));
      else el.removeAttribute('aria-describedby');
    };

    const announceError = (el) => {
      const errEl = el.dataset.error ? document.getElementById(el.dataset.error) : null;
      if (!errEl) return;
      const labelTxt = el.labels?.[0] ? `${el.labels[0].textContent.trim()}: ` : '';
      clearTimeout(announceTimer);
      announcer.textContent = '';
      announcedField = el;
      announceTimer = setTimeout(() => {
        announcer.textContent = labelTxt + errEl.textContent.replace(/\s+/g, ' ').trim();
      }, 450);
    };

    const syncAria = (el, announce = false) => {
      if (!el.matches?.('input, select, textarea')) return;
      const isInvalid = supportsUserInvalid
        ? el.matches(':user-invalid')
        : el.classList.contains('user-invalid-fallback');

      if (isInvalid) {
        if (announce && !announced.has(el)) {
          announceError(el);
          announced.add(el);
        }
        el.setAttribute('aria-invalid', 'true');
        linkError(el, true);
        flagged.add(el);
      } else if (flagged.has(el) || el.getAttribute('aria-invalid') !== 'true') {
        el.setAttribute('aria-invalid', 'false');
        linkError(el, false);
        flagged.delete(el);
        announced.delete(el);
        if (announcedField === el) {
          clearTimeout(announceTimer);
          announcer.textContent = '';
        }
      }
    };

    document.querySelectorAll('input, select, textarea').forEach((el) => syncAria(el));

    document.addEventListener('blur', (e) => {
      const el = e.target;
      if (!el.matches?.('input, select, textarea')) return;
      if (!supportsUserInvalid) {
        const st = dirtyState.get(el) || { interacted: false, blurred: false };
        st.blurred = true;
        dirtyState.set(el, st);
        if (st.interacted) el.classList.toggle('user-invalid-fallback', !el.validity.valid);
      }
      syncAria(el, true);
    }, true);

    document.addEventListener('input', (e) => {
      const el = e.target;
      if (!el.matches?.('input, select, textarea')) return;
      if (!supportsUserInvalid) {
        const st = dirtyState.get(el) || { interacted: false, blurred: false };
        st.interacted = true;
        dirtyState.set(el, st);
        if (st.blurred) el.classList.toggle('user-invalid-fallback', !el.validity.valid);
      }
      if (el.hasAttribute('aria-invalid')) syncAria(el, false);
    });

    document.addEventListener('invalid', (e) => {
      const el = e.target;
      if (!el.matches?.('input, select, textarea')) return;
      if (!supportsUserInvalid) {
        dirtyState.set(el, { interacted: true, blurred: true });
        el.classList.add('user-invalid-fallback');
      }
      announced.add(el);
      syncAria(el, false);
    }, true);

    // Loading state: once a valid form is submitted, its button shows a spinner and the form is
    // marked busy. The button is disabled so a slow connection cannot send the same request twice
    // (a double-tapped quote request would otherwise reach the provider twice).
    document.querySelectorAll('form').forEach((form) => {
      form.addEventListener('submit', (event) => {
        if (!form.checkValidity()) return;

        const submitter = event.submitter instanceof HTMLButtonElement ? event.submitter : null;
        const submitBtn = submitter || form.querySelector('button[type="submit"]');

        form.setAttribute('aria-busy', 'true');
        if (submitBtn) {
          submitBtn.classList.add('is-loading');
          setTimeout(() => { submitBtn.disabled = true; }, 0);
        }
      });
    });

    // Coming back with the browser's Back button can restore the page mid-"loading": reset it.
    window.addEventListener('pageshow', (event) => {
      if (!event.persisted) return;
      document.querySelectorAll('form[aria-busy="true"]').forEach((form) => {
        form.removeAttribute('aria-busy');
        form.querySelectorAll('.btn.is-loading').forEach((btn) => {
          btn.classList.remove('is-loading');
          btn.disabled = false;
        });
      });
    });
  };

  initNavbar();
  initHeroSpotlight();
  initVerticalTabs();
  initHireWizard();
  initDialogs();
  initPortfolioLightbox();
  initAuthEnhancements();
  initShortlist();
  initDeferredValidation();
})();
