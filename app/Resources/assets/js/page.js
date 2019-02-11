import { changeCarousel } from './carousel.js'

const dropdown = document.querySelector('.dropdown')
const items = dropdown.querySelectorAll('.item')

const updateHeightDropdown = () => {
  let active = dropdown.querySelector('.item.active')
  active.style.maxHeight = active.scrollHeight + 'px'
}

const changeItem = item => {
  items.forEach(item => {
    item.style.maxHeight = null
    item.classList.remove('active')
  })
  item.classList.add('active')
  updateHeightDropdown()
  let reference = item.getAttribute('data-carousel-id')
  if (reference) {
    changeCarousel(reference)
  }
}

items.forEach(item => {
  item.addEventListener('click', () => {
    changeItem(item)
  })
})

document.addEventListener('DOMContentLoaded', () => {
  let item = dropdown.querySelector('.item.first')
  if (window.location.hash !== '') {
    item = dropdown.querySelector(`.item[data-slug-id="${window.location.hash}"]`)
  }
  setTimeout(() => {
    changeItem(item)
  }, 500)
})

const inputs = dropdown.querySelectorAll('.input')

inputs.forEach(input => {
  input.setAttribute('autocomplete', 'nope')
})
