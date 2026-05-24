const tagContainer = document.getElementById('tagContainer');
const inputTagsHidden = document.getElementById('itemTagsHidden');
const itemTagsInput = document.getElementById('itemTagsInput');

function createTag(tagName) {
    const tag = document.createElement('div');
    tag.className = 'tag';
    tag.innerHTML = `
        <span class="tag-remove" onclick="removeTag(this)">×</span>
        ${tagName}
    `;
    return tag;
}

function handleTagInput(event) {
    let key = event.keyCode;
    if (key === 13) {
        addTagFromInput();
    }
}

function addTagFromInput() {
    const inputValue = itemTagsInput.value.trim();
    if (inputValue) {
        const tag = createTag(inputValue);
        tagContainer.appendChild(tag);
        inputTagsHidden.value += (inputTagsHidden.value ? ', ' : '') + inputValue;
        itemTagsInput.value = ''; // Clear the input after adding tag
    }
}

document.addEventListener('keydown', handleTagInput);

function removeTag(tagRemoveButton) {
    const tag = tagRemoveButton.parentNode;
    const tagName = tag.textContent.trim();
    tagContainer.removeChild(tag);

    // Remove the tag from the hidden input value
    const tagsArray = inputTagsHidden.value.split(', ');
    const tagIndex = tagsArray.indexOf(tagName);
    if (tagIndex !== -1) {
        tagsArray.splice(tagIndex, 1);
        inputTagsHidden.value = tagsArray.join(', ');
    }
}

// Function to add a tag
function addTag(tagName) {
    const tagContainer = document.getElementById('tagContainer');
    const inputTagsHidden = document.getElementById('itemTagsHidden');
    if (tagName.length > 0) {
        const tag = createTag(tagName);
        tagContainer.appendChild(tag);
        inputTagsHidden.value += (inputTagsHidden.value ? ', ' : '') + tagName;
    }
}
