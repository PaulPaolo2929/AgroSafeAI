/* assets/js/app.js */

document.addEventListener("DOMContentLoaded", function() {
    const navLinks = document.querySelectorAll('.nav-link');
    const sections = document.querySelectorAll('.view-section');

    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();

            // 1. Remove active class from all links
            navLinks.forEach(l => l.classList.remove('active'));
            
            // 2. Add active class to clicked link
            this.classList.add('active');

            // 3. Hide all sections
            sections.forEach(s => s.classList.remove('active'));

            // 4. Show target section
            const targetId = this.getAttribute('data-target');
            document.getElementById(targetId).classList.add('active');
        });
    });
});