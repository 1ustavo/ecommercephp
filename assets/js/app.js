document.addEventListener("DOMContentLoaded", () => {
    const optionGroups = document.querySelectorAll(".payment-options");

    optionGroups.forEach((group) => {
        group.addEventListener("click", (event) => {
            const option = event.target.closest(".payment-option");
            if (!option) {
                return;
            }

            const radio = option.querySelector("input[type='radio']");
            if (!radio || radio.disabled) {
                return;
            }

            radio.checked = true;
            option.parentElement.querySelectorAll(".payment-option").forEach((item) => {
                item.classList.toggle("selected", item === option);
            });
            radio.dispatchEvent(new Event("change", { bubbles: true }));
        });
    });
});
