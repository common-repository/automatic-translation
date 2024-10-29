jQuery(document).ready(function(){
    const select = document.querySelectorAll('.selectBtn');
    const option = document.querySelectorAll('.option');
    const selectflag = document.querySelectorAll('.translatorsc-flag-img');
    const selectlang = document.querySelectorAll('.selectBtn span');
    let index = 1;

    select.forEach(a => {
        a.addEventListener('click', b => {
            const next = b.target.nextElementSibling;
            if(next !== null){
                next.classList.toggle('toggle');
                next.style.zIndex = index++;
            }
        })
    })
    option.forEach(a => {
        a.addEventListener('click', b => {
            b.target.parentElement.classList.remove('toggle');          
            const parent = b.target.closest('.select').children[0];
            parent.setAttribute('data-type', b.target.getAttribute('data-type'));
            parent.innerText = b.target.innerText;
        })
    })
    selectflag.forEach(a => {
        a.addEventListener('click', b => {
            const next = b.target.parentNode.nextElementSibling;
            if(next !== null){
                next.classList.toggle('toggle');
                next.style.zIndex = index++;
            }
        })
    })
    selectlang.forEach(a => {
        a.addEventListener('click', b => {
            const next = b.target.parentNode.nextElementSibling;
            if(next !== null){
                next.classList.toggle('toggle');
                next.style.zIndex = index++;
            }
        })
    })
});
