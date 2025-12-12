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


// ... existing navigation code ...

// --- LIVE MARKET UPDATER ---
function updateMarketData() {
    const marketSection = document.getElementById('market');
    
    // Only update if the user is looking at the Market tab
    if (marketSection.classList.contains('active')) {
        fetch('data/get_live_prices.php')
            .then(response => response.json())
            .then(data => {
                // Update the HTML elements by ID
                document.getElementById('live-rate').innerText = '₱' + data.rate;
                document.getElementById('price-crop').innerText = '₱' + data.crop_php;
                document.getElementById('price-chem').innerText = '₱' + data.fungicide_php;
                document.getElementById('price-labor').innerText = '₱' + data.labor_php;
                document.getElementById('last-update').innerText = 'Updated: ' + data.timestamp;
                
                // Add a green flash effect to show it updated
                document.querySelectorAll('.price-value').forEach(el => {
                    el.style.color = '#2ecc71'; // Flash Green
                    setTimeout(() => el.style.color = '', 500); // Return to normal
                });
            })
            .catch(error => console.error('Error fetching market data:', error));
    }
}

// Run this every 5000 milliseconds (5 seconds)
setInterval(updateMarketData, 5000);

// Run once immediately on load
updateMarketData();

