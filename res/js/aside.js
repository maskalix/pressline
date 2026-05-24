document.addEventListener("DOMContentLoaded", function () {
    var menuItems = document.querySelectorAll('#menu-list a');
    var currentFile = window.location.pathname.split('/').pop();
    var currFileWithId = "";

    menuItems.forEach(function (item) {
        var fileName = item.getAttribute('href');
        var fileNameNoQuery = fileName.split('?')[0];
        if (fileName === currentFile || (fileName.includes('uzivatel.php') && currFileWithId === "uzivatel.php?id=<?php echo $sessionId?>") || (currentFile === fileNameNoQuery && fileNameNoQuery !== 'uzivatel.php')) {
            item.classList.add('menu-active');
            openAssociatedDropdown(item);
        }
    });

    /* DROPDOWNS */
    var dropdowns = document.querySelectorAll('.dropdown > .dropdown-toggle');
    dropdowns.forEach(function (dropdownToggle) {
        dropdownToggle.addEventListener('click', function (event) {
            event.preventDefault();
            var dropdownContent = this.parentNode.querySelector('.dropdown-menu');
            // Close all other open collapsibles
            closeAllDropdownsExcept(dropdownContent);
            // Toggle the 'open' class
            dropdownContent.classList.toggle('open');
        });
    });

    function closeAllDropdownsExcept(excludeDropdown) {
        var allDropdowns = document.querySelectorAll('.dropdown-menu');
        allDropdowns.forEach(function (dropdownContent) {
            if (dropdownContent !== excludeDropdown && dropdownContent.classList.contains('open')) {
                dropdownContent.classList.remove('open');
            }
        });
    }

    function openAssociatedDropdown(item) {
        var dropdown = findClosestDropdown(item);
        if (dropdown) {
            dropdown.classList.add('open');
        }
    }

    function findClosestDropdown(element) {
        var parent = element.parentElement.parentElement;
        while (parent) {
            if (parent.classList.contains('dropdown-menu')) {
                return parent;
            }
            parent = parent.parentElement;
        }
        return null;
    }
});