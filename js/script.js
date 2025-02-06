document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const suggestionsBox = document.getElementById('suggestions');

    // Function to fetch suggestions and display the suggestions box
    function fetchSuggestions(query) {
        if (query.length === 0) {
            suggestionsBox.innerHTML = '';
            return;
        }

        fetch(`get-suggestions.php?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                // Clear previous suggestions
                suggestionsBox.innerHTML = '';

                if (data.length > 0) {
                    // Show the suggestions box if there are results
                    suggestionsBox.style.display = 'block';
                } else {
                    // Hide the suggestions box if no results
                    suggestionsBox.style.display = 'none';
                }

                // Add each suggestion as a clickable item with an icon
                data.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'suggestion-item';

                    // Determine the icon based on the entity type
                    const entityTypeLower = item.entityType.toLowerCase();
                    const iconSrc = `images/icons/${entityTypeLower}.svg`;

                    // Create the img element for the icon with a specific class
                    const iconImg = document.createElement('img');
                    iconImg.src = iconSrc;
                    iconImg.alt = `${item.entityType} Icon`;
                    iconImg.classList.add('suggestion-icon'); // add the specific class

                    // Create a span for the text
                    const textSpan = document.createElement('span');
                    textSpan.textContent = `${item.entityType}: ${item.name}`;

                    // Append the icon and text to the suggestion item div
                    div.appendChild(iconImg);
                    div.appendChild(textSpan);

                    // When a suggestion is clicked, redirect to the search results page
                    div.addEventListener('click', () => {
                        // Redirect to the results page with the selected suggestion as query parameter
                        window.location.href = `search-results.php?q=${encodeURIComponent(item.name)}`;
                    });

                    suggestionsBox.appendChild(div);
                });
            })
            .catch(error => console.error('Error fetching suggestions:', error));
    }

    // Show suggestions when the user clicks into the input field
    searchInput.addEventListener('focus', function() {
        const query = searchInput.value.trim();
        fetchSuggestions(query); // Fetch suggestions based on current input value
    });

    // When the user types in the search input, fetch suggestions via AJAX
    searchInput.addEventListener('input', function() {
        const query = searchInput.value.trim();
        fetchSuggestions(query);
    });

    // Hide suggestions when clicking outside the search input and suggestions box
    document.addEventListener('click', function(event) {
        if (!searchInput.contains(event.target) && !suggestionsBox.contains(event.target)) {
            suggestionsBox.style.display = 'none';
        }
    });
});

// Function to toggle the dropdown menu and change the chevron icon
function toggleDropdownMenu() {
    var dropdown = document.getElementById('dropdown-menu');
    var chevronIcon = document.getElementById('chevron-icon');
    if (dropdown.style.display === 'none' || !dropdown.style.display) {
        dropdown.style.display = 'block';
        chevronIcon.src = 'images/icons/chevron-down.svg'; // Path to chevron down icon
    } else {
        dropdown.style.display = 'none';
        chevronIcon.src = 'images/icons/chevron-right.svg'; // Path to chevron right icon
    }
}
