/***
 *      _____ _                                                                   
 *     |_   _| |                                                                  
 *       | | | |_ ___ _ __ ___  ___     _ __   ___ _ __    _ __   __ _  __ _  ___ 
 *       | | | __/ _ \ '_ ` _ \/ __|   | '_ \ / _ \ '__|  | '_ \ / _` |/ _` |/ _ \
 *      _| |_| ||  __/ | | | | \__ \   | |_) |  __/ |     | |_) | (_| | (_| |  __/
 *     |_____|\__\___|_| |_| |_|___/   | .__/ \___|_|     | .__/ \__,_|\__, |\___|
 *                                     | |                | |           __/ |     
 *                                     |_|                |_|          |___/      
 */
function changePerPage() {
    var perPageSelect = document.getElementById("perPageSelect");
    var selectedPerPage = perPageSelect.options[perPageSelect.selectedIndex].value;
    var currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('per_page', selectedPerPage);
    currentUrl.searchParams.set('p', 1);
    window.location.href = currentUrl.toString();
}

// Select/deselectall checkboxes (allCheckboxes())
function toggleCheckboxes(name = 'selectAll') {
    const checkboxes = document.querySelectorAll('.delete-checkbox');
    const allCheckbox = document.getElementById(name);
    checkboxes.forEach(item => {
        item.checked = allCheckbox.checked;
    });
}

/***
 *      _____       _      _           _ _                  __       __  
 *     |  __ \     | |    | |         (_) |                / /       \ \ 
 *     | |  | | ___| | ___| |_ ___     _| |_ ___ _ __ ___ | |   ___   | |
 *     | |  | |/ _ \ |/ _ \ __/ _ \   | | __/ _ \ '_ ` _ \| |  / __|  | |
 *     | |__| |  __/ |  __/ ||  __/   | | ||  __/ | | | | | |  \__ \  | |
 *     |_____/ \___|_|\___|\__\___|   |_|\__\___|_| |_| |_| |  |___/  | |
 *                                                         \_\       /_/ 
 *  Description:
 *  - with/without prompt                                                     
 */
function deleteSelectedPrompt(type) {
    const checkboxes = document.querySelectorAll('.delete-checkbox:checked');
    if (checkboxes.length === 0) {
        alert('Vyber položky, které chceš smazat.');
        return;
    }

    if (confirm('Chceš doopravdy smazat vybrané položky? (' + checkboxes.length + ')')) {
        checkboxes.forEach(checkbox => {
            if (type === 'category') {
                defaultCategory(checkbox.value);
            }
            deleteItem(checkbox.value, type);
        });
    }
}

function deletePrompt(id, type) {
    if (confirm('Chceš doopravdy smazat tuto položku?')) {
        // Default category to 1 for articles
        if (type === 'category') {
            defaultCategory(id);
        }
        deleteItem(id, type);
    }
}

function deleteItem(id, type) {
    const data = new FormData();
    data.append('action', 'delete');
    data.append('type', type);
    data.append('id', id);
    if (type === 'media') {
        const fileData = new FormData();
        fileData.append('action', 'delete');
        fileData.append('type', type);
        fileData.append('id', id);
        post('./ajax/deleteMedia.php', fileData);
    }
    post('./setter-proxy.php', data);
}

function defaultCategory(id) {
    const data = new FormData();
    data.append('action', 'edit');
    data.append('type', 'article');
    data.append('where', 'category');
    data.append('id', id);
    data.append('category', 1);
    post('./setter-proxy.php', data);
}
/***
 *       _____                     _     
 *      / ____|                   | |    
 *     | (___   ___  __ _ _ __ ___| |__  
 *      \___ \ / _ \/ _` | '__/ __| '_ \ 
 *      ____) |  __/ (_| | | | (__| | | |
 *     |_____/ \___|\__,_|_|  \___|_| |_|
 *                                       
 *                                       
 */
function search() {
    const searchQuery = document.getElementById("searchInput").value;
    window.location.href = "?search=" + encodeURIComponent(searchQuery);
}

document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById("searchInput");
    if (searchInput) {
        searchInput.addEventListener("keydown", function(event) {
            if (event.key === "Enter" || event.keyCode === 13 || event.code === "Enter") {
                search();
            }
        });
    }
});

/***
 *       _____      _   _            
 *      / ____|    | | | |           
 *     | (___   ___| |_| |_ ___ _ __ 
 *      \___ \ / _ \ __| __/ _ \ '__|
 *      ____) |  __/ |_| ||  __/ |   
 *     |_____/ \___|\__|\__\___|_|   
 *                                   
 *  Description: 
 *  - POST request to "setter-proxy.php"
 *  - send in FormData object               
 */
function post(destination, data) {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta && data instanceof FormData) {
        data.append('csrf_token', csrfMeta.content);
    }
    fetch(destination, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'fetch',
            'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : ''
        },
        body: data,
        cache: 'no-cache'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text(); // Get echoed PHP output
    })
    .then(text => {
       //reloadWithParams(text.state, text); // DEBUG LINE FOR NON-JSON
        reloadWithParams(JSON.parse(text).state, JSON.parse(text).message);
       //reloadWithParams("positive", text);
    })
    .catch(error => {
        //console.error('Error:', error);
        reloadWithParams("negative", "Chyba při aplikaci požadavku" + error);
    });
}
/***
 *      _____  _           _                                                           _ 
 *     |  __ \(_)         | |                                                         | |
 *     | |  | |_ ___ _ __ | | __ _ _   _     _ __   __ _ ___ _____      _____  _ __ __| |
 *     | |  | | / __| '_ \| |/ _` | | | |   | '_ \ / _` / __/ __\ \ /\ / / _ \| '__/ _` |
 *     | |__| | \__ \ |_) | | (_| | |_| |   | |_) | (_| \__ \__ \\ V  V / (_) | | | (_| |
 *     |_____/|_|___/ .__/|_|\__,_|\__, |   | .__/ \__,_|___/___/ \_/\_/ \___/|_|  \__,_|
 *                  | |             __/ |   | |                                          
 *                  |_|            |___/    |_|                                          
 */

function showPassword(element,target) {
    const type = element.checked ? "text" : "password";
    target.type = type;
}