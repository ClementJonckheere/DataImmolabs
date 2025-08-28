document.addEventListener('DOMContentLoaded', function () {
    const data = {
        labels: JSON.parse(document.getElementById('chart-data-labels').textContent),
        datasets: [{
            label: 'Nombre de transactions',
            data: JSON.parse(document.getElementById('chart-data-values').textContent),
            backgroundColor: 'rgba(75, 192, 192, 0.5)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 1
        }]
    };

    new Chart(
        document.getElementById('priceRangeChart'),
        {
            type: 'bar',
            data: data,
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        }
    );
});


$(document).ready(async function () {
    const select = $('#ville-select');
    const params = new URLSearchParams(window.location.search);
    const selectedVille = params.get("ville");

    try {
        const response = await fetch("/api/villes");
        const data = await response.json();

        data.villes.forEach(ville => {
            const option = new Option(ville, ville, false, ville === selectedVille);
            select.append(option);
        });

        select.select2({
            placeholder: "Rechercher une ville",
            allowClear: true,
            width: '100%'
        });

        select.on('change', function () {
            const ville = $(this).val();
            if (ville) {
                params.set("ville", ville);
            } else {
                params.delete("ville");
            }
            window.location.search = params.toString();
        });

    } catch (error) {
        console.error("Erreur lors du chargement des villes :", error);
    }
});

