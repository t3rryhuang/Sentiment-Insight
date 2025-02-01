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

