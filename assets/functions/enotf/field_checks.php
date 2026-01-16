<script>
    const inputElements = document.querySelectorAll('.edivi__input-check');

    function toggleInputChecked(inputElement) {
        if (inputElement.tagName === 'SELECT') {
            const selectedOption = inputElement.querySelector('option:checked');
            if (selectedOption && !selectedOption.disabled) {
                inputElement.classList.add('edivi__input-checked');
            } else {
                inputElement.classList.remove('edivi__input-checked');
            }
        } else {
            if (inputElement.value.trim() === '') {
                inputElement.classList.remove('edivi__input-checked');
            } else {
                inputElement.classList.add('edivi__input-checked');
            }
        }

        const groupContainer = inputElement.closest('.edivi__box');
        const groupHeading = groupContainer ? groupContainer.querySelector('h5.edivi__group-check') : null;

        if (groupHeading) {
            inputElement.style.borderLeft = '0';
        } else {
            inputElement.style.borderLeft = '';
        }
    }

    function checkGroupStatus() {
        const groupHeadings = document.querySelectorAll('h5.edivi__group-check');
        groupHeadings.forEach(groupHeading => {
            const groupContainer = groupHeading.closest('.edivi__box');
            if (!groupContainer) return;

            const groupInputs = groupContainer.querySelectorAll('.edivi__input-check');
            let filledCount = 0;
            let totalCount = groupInputs.length;

            groupInputs.forEach(input => {
                let isFilled = false;

                if (input.tagName === 'SELECT') {
                    const selectedOption = input.querySelector('option:checked');
                    if (selectedOption && !selectedOption.disabled) {
                        isFilled = true;
                    }
                } else if (input.value && input.value.trim() !== '') {
                    isFilled = true;
                }

                if (isFilled) {
                    filledCount++;
                }

                input.style.borderLeft = '0';
            });

            groupHeading.classList.remove('edivi__group-checked', 'edivi__group-partchecked');

            if (filledCount === totalCount && totalCount > 0) {
                groupHeading.classList.add('edivi__group-checked');
            } else if (filledCount > 0) {
                groupHeading.classList.add('edivi__group-partchecked');
            }
        });
    }

    inputElements.forEach(inputElement => {
        toggleInputChecked(inputElement);
        inputElement.addEventListener('input', () => {
            toggleInputChecked(inputElement);
            checkGroupStatus();
        });
        inputElement.addEventListener('change', () => {
            toggleInputChecked(inputElement);
            checkGroupStatus();
        });
    });

    document.addEventListener('DOMContentLoaded', checkGroupStatus);

    // Re-check after custom dropdowns are initialized
    setTimeout(function() {
        checkGroupStatus();
    }, 500);

    document.addEventListener('DOMContentLoaded', function() {
        const clickableBoxes = document.querySelectorAll('.edivi__box-clickable');

        clickableBoxes.forEach(function(box) {
            box.addEventListener('click', function() {
                window.location.href = this.getAttribute('data-href');
            });
        });
    });
</script>