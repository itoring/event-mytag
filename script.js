document.addEventListener("DOMContentLoaded", function () {
    const toggleButtons = document.querySelectorAll(".tag-toggle");

    toggleButtons.forEach(btn => {
        btn.addEventListener("click", () => {
            btn.classList.toggle("tag-toggle--selected");
        });
    });
});