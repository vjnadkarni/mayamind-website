/* ============================================
   MayaMind Website — Main JavaScript
   ============================================ */

document.addEventListener('DOMContentLoaded', () => {

  // ---------- Navigation ----------
  const navbar = document.getElementById('navbar');
  const navToggle = document.getElementById('navToggle');
  const navLinks = document.getElementById('navLinks');

  // Scroll-based navbar background
  const onScroll = () => {
    navbar.classList.toggle('scrolled', window.scrollY > 50);
  };
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  // Mobile menu toggle
  navToggle.addEventListener('click', () => {
    navLinks.classList.toggle('open');
    const spans = navToggle.querySelectorAll('span');
    if (navLinks.classList.contains('open')) {
      spans[0].style.transform = 'rotate(45deg) translate(5px, 5px)';
      spans[1].style.opacity = '0';
      spans[2].style.transform = 'rotate(-45deg) translate(6px, -6px)';
    } else {
      spans[0].style.transform = '';
      spans[1].style.opacity = '';
      spans[2].style.transform = '';
    }
  });

  // Close mobile menu on link click
  navLinks.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
      navLinks.classList.remove('open');
      const spans = navToggle.querySelectorAll('span');
      spans[0].style.transform = '';
      spans[1].style.opacity = '';
      spans[2].style.transform = '';
    });
  });

  // ---------- Scroll Animations (Intersection Observer) ----------
  const animateElements = document.querySelectorAll('.animate-in');
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        observer.unobserve(entry.target);
      }
    });
  }, {
    threshold: 0.15,
    rootMargin: '0px 0px -60px 0px'
  });

  animateElements.forEach(el => observer.observe(el));

  // ---------- Animated Stat Counters ----------
  const statNumbers = document.querySelectorAll('.stat-number[data-target]');
  const statsObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const el = entry.target;
        const target = parseInt(el.dataset.target);
        animateCounter(el, target);
        statsObserver.unobserve(el);
      }
    });
  }, { threshold: 0.5 });

  statNumbers.forEach(el => statsObserver.observe(el));

  function animateCounter(el, target) {
    const duration = 1500;
    const start = performance.now();
    const update = (now) => {
      const elapsed = now - start;
      const progress = Math.min(elapsed / duration, 1);
      // Ease out cubic
      const eased = 1 - Math.pow(1 - progress, 3);
      el.textContent = Math.round(eased * target);
      if (progress < 1) requestAnimationFrame(update);
    };
    requestAnimationFrame(update);
  }

  // ---------- Features Carousel (Swiper) ----------
  new Swiper('.features-swiper', {
    slidesPerView: 1,
    spaceBetween: 24,
    loop: true,
    autoplay: {
      delay: 5000,
      disableOnInteraction: true,
    },
    pagination: {
      el: '.swiper-pagination',
      clickable: true,
    },
    navigation: {
      nextEl: '.swiper-button-next',
      prevEl: '.swiper-button-prev',
    },
    breakpoints: {
      640: {
        slidesPerView: 2,
        spaceBetween: 24,
      },
      1024: {
        slidesPerView: 3,
        spaceBetween: 32,
      },
    },
  });

  // ---------- Waitlist Form ----------
  const form = document.getElementById('waitlistForm');
  const submitBtn = document.getElementById('submitBtn');
  const btnText = submitBtn.querySelector('.btn-text');
  const btnLoading = submitBtn.querySelector('.btn-loading');
  const successDiv = document.getElementById('waitlistSuccess');
  const confirmedDiv = document.getElementById('waitlistConfirmed');
  const confirmEmailSpan = document.getElementById('confirmEmail');
  const otherRadio = document.querySelector('input[value="other"]');
  const otherInput = document.getElementById('otherInput');

  // Show/hide "Other" text input
  document.querySelectorAll('input[name="interest"]').forEach(radio => {
    radio.addEventListener('change', () => {
      otherInput.style.display = radio.value === 'other' && radio.checked ? 'block' : 'none';
      if (radio.value === 'other' && radio.checked) {
        document.getElementById('otherReason').focus();
      }
    });
  });

  // Form submission
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const email = document.getElementById('email').value.trim();
    const interest = document.querySelector('input[name="interest"]:checked');

    if (!email || !interest) return;

    let interestValue = interest.value;
    if (interestValue === 'other') {
      const otherReason = document.getElementById('otherReason').value.trim();
      if (!otherReason) {
        document.getElementById('otherReason').focus();
        return;
      }
      interestValue = 'other: ' + otherReason;
    }

    // Show loading state
    btnText.style.display = 'none';
    btnLoading.style.display = 'inline-flex';
    submitBtn.disabled = true;

    try {
      const response = await fetch('api/subscribe.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, interest: interestValue }),
      });

      const result = await response.json();

      if (result.success) {
        form.style.display = 'none';
        confirmEmailSpan.textContent = email;
        successDiv.style.display = 'block';
      } else {
        alert(result.message || 'Something went wrong. Please try again.');
        btnText.style.display = 'inline';
        btnLoading.style.display = 'none';
        submitBtn.disabled = false;
      }
    } catch (err) {
      alert('Unable to connect. Please check your internet and try again.');
      btnText.style.display = 'inline';
      btnLoading.style.display = 'none';
      submitBtn.disabled = false;
    }
  });

  // Check URL for confirmation token
  const params = new URLSearchParams(window.location.search);
  if (params.get('confirmed') === 'true') {
    // User arrived via confirmation link
    const waitlistSection = document.getElementById('waitlist');
    if (waitlistSection) {
      form.style.display = 'none';
      confirmedDiv.style.display = 'block';
      waitlistSection.scrollIntoView({ behavior: 'smooth' });
    }
  }

  // ---------- Smooth scroll for anchor links ----------
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', (e) => {
      const target = document.querySelector(anchor.getAttribute('href'));
      if (target) {
        e.preventDefault();
        const offset = navbar.offsetHeight;
        const top = target.getBoundingClientRect().top + window.scrollY - offset;
        window.scrollTo({ top, behavior: 'smooth' });
      }
    });
  });

});
