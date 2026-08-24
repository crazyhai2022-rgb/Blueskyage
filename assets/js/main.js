/* ============================================================
   BLUESKY AGENCY — core script
   ============================================================ */

// --- CONFIG ---------------------------------------------------
const WHATSAPP_NUMBER = "919507196648"; // +91 9507196648, no + or spaces

// --- LOADER -----------------------------------------------------
window.addEventListener("load", () => {
  const loader = document.getElementById("loader");
  if (!loader) return;
  setTimeout(() => {
    loader.classList.add("hide");
    document.body.style.overflow = "";
  }, 900);
});

// prevent scroll flash while loading
document.body.style.overflow = "hidden";
window.addEventListener("DOMContentLoaded", () => {
  setTimeout(() => { document.body.style.overflow = ""; }, 1000);
});

// --- NAVBAR: scroll state + mobile toggle -----------------------
const navbar = document.querySelector(".navbar");
const navToggle = document.querySelector(".nav-toggle");
const navLinks = document.querySelector(".nav-links");

if (navbar) {
  const onScroll = () => {
    navbar.classList.toggle("scrolled", window.scrollY > 30);
  };
  onScroll();
  window.addEventListener("scroll", onScroll, { passive: true });
}

if (navToggle && navLinks) {
  navToggle.addEventListener("click", () => {
    navLinks.classList.toggle("open");
  });
  navLinks.querySelectorAll("a").forEach(a => {
    a.addEventListener("click", () => navLinks.classList.remove("open"));
  });
}

// --- REVEAL ON SCROLL --------------------------------------------
const revealEls = document.querySelectorAll(".reveal");
if (revealEls.length) {
  const io = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add("in");
        io.unobserve(e.target);
      }
    });
  }, { threshold: 0.15 });
  revealEls.forEach(el => io.observe(el));
}

// --- WHATSAPP HELPERS ---------------------------------------------
function openWhatsApp(message) {
  const url = `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(message)}`;
  window.location.href = url;
}

// Generic "quick chat" WhatsApp buttons: data-wa-message="..."
document.querySelectorAll("[data-wa-message]").forEach(btn => {
  btn.addEventListener("click", (e) => {
    e.preventDefault();
    openWhatsApp(btn.getAttribute("data-wa-message"));
  });
});

// --- FREE TRIAL BUTTONS (on services/home/products pages) ---------
// Buttons with class .js-trial-btn just jump straight to the trial form page.
// (Kept simple — the actual WhatsApp send happens after the form is filled.)

// --- FORM: GET STARTED (paid plan) --------------------------------
const getStartedForm = document.getElementById("getStartedForm");
if (getStartedForm) {
  getStartedForm.addEventListener("submit", (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(getStartedForm).entries());
    const plan = data.plan || "Meta Agency Ad Account";

    // NOTE: Razorpay integration point.
    // Replace YOUR_RAZORPAY_KEY_ID below with your real key, then this will
    // open the Razorpay checkout. On successful payment it redirects to WhatsApp
    // with all the submitted details.
    const RAZORPAY_KEY_ID = "YOUR_RAZORPAY_KEY_ID";
    const amount = plan.includes("Pro") ? 249900 : 149900; // in paise

    function sendToWhatsApp(paymentId) {
      const msg =
`New Order — ${plan}
----------------------------
Name: ${data.name}
Number: ${data.number}
Business Name: ${data.business || "-"}
BM ID: ${data.bmid}
Email: ${data.email}
Plan: ${plan}
Payment ID: ${paymentId || "Pending"}
----------------------------
Please confirm and activate my account.`;
      openWhatsApp(msg);
    }

    if (window.Razorpay && RAZORPAY_KEY_ID !== "YOUR_RAZORPAY_KEY_ID") {
      const rzp = new Razorpay({
        key: RAZORPAY_KEY_ID,
        amount: amount,
        currency: "INR",
        name: "BlueSky Agency",
        description: plan,
        image: "../assets/img/logo-mark.png",
        handler: function (response) {
          sendToWhatsApp(response.razorpay_payment_id);
        },
        prefill: { name: data.name, email: data.email, contact: data.number },
        theme: { color: "#3b62ff" }
      });
      rzp.open();
    } else {
      // Razorpay key not yet configured — go straight to WhatsApp so the flow still works.
      sendToWhatsApp(null);
    }
  });
}

// --- FORM: PRODUCT CHECKOUT -----------------------------------------
const productForm = document.getElementById("productForm");
if (productForm) {
  const params = new URLSearchParams(window.location.search);
  const productName = params.get("product") || "BlueSky Product";
  const productPrice = params.get("price") || "";

  const nameEl = document.getElementById("checkoutProductName");
  const priceEl = document.getElementById("checkoutProductPrice");
  if (nameEl) nameEl.textContent = productName;
  if (priceEl) priceEl.textContent = productPrice;

  productForm.addEventListener("submit", (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(productForm).entries());
    const msg =
`New Product Order
----------------------------
Product: ${productName}
Price: ${productPrice}
Name: ${data.name}
Email: ${data.email}
Mobile: ${data.mobile}
----------------------------
Please share payment details to proceed.`;
    openWhatsApp(msg);
  });
}

// --- FORM: CONTACT PAGE ----------------------------------------------
const contactForm = document.getElementById("contactForm");
if (contactForm) {
  contactForm.addEventListener("submit", (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(contactForm).entries());
    const msg =
`New Website Enquiry
----------------------------
Name: ${data.name}
Email: ${data.email}
Number: ${data.number}
Message: ${data.message || "-"}`;
    openWhatsApp(msg);
  });
}

// --- PASSWORD VISIBILITY TOGGLE --------------------------------------
// Lets people check what they typed. The field returns to hidden on submit
// so a password is never left on screen after leaving the page.
(function () {
  document.querySelectorAll('.pw-toggle').forEach(function (btn) {
    const field = btn.closest('.pw-field');
    const input = field && field.querySelector('input');
    if (!input) return;

    const openEye = btn.querySelector('.pw-open');
    const shutEye = btn.querySelector('.pw-shut');

    function setShown(shown) {
      input.type = shown ? 'text' : 'password';
      openEye.hidden = shown;
      shutEye.hidden = !shown;
      const label = shown ? 'Hide password' : 'Show password';
      btn.setAttribute('aria-label', label);
      btn.setAttribute('title', label);
    }

    btn.addEventListener('click', function () {
      setShown(input.type === 'password');
      input.focus();
    });

    const form = input.closest('form');
    if (form) form.addEventListener('submit', function () { setShown(false); });
  });
})();

// --- ACCOUNT DROPDOWN -------------------------------------------------
(function () {
  const wrap = document.querySelector('[data-acct]');
  if (!wrap) return;

  const btn  = wrap.querySelector('.acct-btn');
  const menu = wrap.querySelector('.acct-menu');

  function close() {
    menu.hidden = true;
    wrap.classList.remove('open');
    btn.setAttribute('aria-expanded', 'false');
  }

  function open() {
    menu.hidden = false;
    wrap.classList.add('open');
    btn.setAttribute('aria-expanded', 'true');
  }

  btn.addEventListener('click', function (e) {
    e.stopPropagation();
    menu.hidden ? open() : close();
  });

  // clicking anywhere else, or pressing Escape, dismisses it
  document.addEventListener('click', function (e) {
    if (!menu.hidden && !wrap.contains(e.target)) close();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !menu.hidden) { close(); btn.focus(); }
  });
})();
