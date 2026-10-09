const menuButton = document.querySelector(".menu-toggle");
const navigation = document.querySelector(".site-nav");

if (menuButton && navigation) {
  menuButton.addEventListener("click", () => {
    const isOpen = navigation.classList.toggle("open");
    menuButton.setAttribute("aria-expanded", String(isOpen));
    menuButton.setAttribute("aria-label", isOpen ? "Close menu" : "Open menu");
    menuButton.textContent = isOpen ? "×" : "☰";
  });

  navigation.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", () => {
      navigation.classList.remove("open");
      menuButton.setAttribute("aria-expanded", "false");
      menuButton.setAttribute("aria-label", "Open menu");
      menuButton.textContent = "☰";
    });
  });
}

const year = document.querySelector("[data-year]");
if (year) year.textContent = String(new Date().getFullYear());

const contactForm = document.querySelector("#contact-form");
if (contactForm) {
  contactForm.addEventListener("submit", (event) => {
    event.preventDefault();
    if (!contactForm.reportValidity()) return;

    const fields = new FormData(contactForm);
    const subject = `Website enquiry from ${fields.get("name")}`;
    const body = [
      `Name: ${fields.get("name")}`,
      `Email: ${fields.get("email")}`,
      `Phone: ${fields.get("phone") || "Not provided"}`,
      `Service: ${fields.get("service") || "Not specified"}`,
      "",
      "Message:",
      fields.get("message"),
    ].join("\n");

    window.location.href = `mailto:woodhask.ediomu@woodhask.com?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
  });
}
