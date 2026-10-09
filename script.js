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
    const readField = (name) => {
      const value = fields.get(name);
      return typeof value === "string" ? value.trim() : "";
    };
    const name = readField("name").replace(/[\r\n]+/g, " ");
    const email = readField("email");
    const phone = readField("phone").replace(/[\r\n]+/g, " ");
    const allowedServices = new Set([
      "Audit & assurance",
      "Tax advisory",
      "Accounting & outsourcing",
      "Advisory",
      "Other enquiry",
    ]);
    const submittedService = readField("service");
    const service = allowedServices.has(submittedService)
      ? submittedService
      : submittedService
        ? "Other enquiry"
        : "Not specified";
    const message = readField("message");
    const subject = `Website enquiry from ${name}`;
    const body = [
      `Name: ${name}`,
      `Email: ${email}`,
      `Phone: ${phone || "Not provided"}`,
      `Service: ${service}`,
      "",
      "Message:",
      message,
    ].join("\n");

    window.location.href = `mailto:woodhask.ediomu@woodhask.com?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
  });
}
