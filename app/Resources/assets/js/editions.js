/* global flashbags */
import $ from 'jquery'
import JsBarcode from 'jsbarcode'
import 'jquery-zoom-js'
import { upCartBox } from './popup'
import { limitMenuReduced } from './variables'
import I18n from './i18n'
import { patchProduct, getCartCount } from './order-api.js'

/* Translations */

let i18n = new I18n()

JsBarcode('.barcode').init()

$('.slide .image').zoom()
let zoom = true

const handleWindowResize = () => {
  if (window.innerWidth >= limitMenuReduced) {
    if (!zoom) {
      $('.slide .image').zoom()
      zoom = true
    }
  } else {
    if (zoom) {
      $('.slide .image').trigger('zoom.destroy')
      zoom = false
    }
  }
}

window.onresize = () => {
  handleWindowResize()
}

const themesForm = document.querySelector('.filter.themes form')
const filtersForm = document.querySelector('.filters form')

if (themesForm != null) {
  themesForm.onchange = event => {
    const themes = document.querySelectorAll('.filter.themes input:checked')
    const input = document.querySelector('.filter.themes input.value')
    let values = []
    themes.forEach(function (element) {
      values.push(element.value)
    })
    input.value = values.join('|')
    event.currentTarget.submit()
  }
}

if (filtersForm != null) {
  document.querySelector('.filters form').onchange = event => {
    event.currentTarget.submit()
  }
}

function changeThumb (direction) {
  let thumbnails = $('.thumbnails')
  let prev = $('.thumb.active', thumbnails)
  let next = prev[direction]()
  if (!next.length) {
    next = (direction === 'next')
      ? $('.thumb:first', thumbnails)
      : $('.thumb:last', thumbnails)
  }
  next.addClass('active')
  prev.removeClass('active')
}

// use xhr to render the cart span number
const cartCountTemplate = _.template($('.cartCount-template').html())

function updateCartCountRender () {
  getCartCount().then((res) => {
    $('.cartCount-render').html(cartCountTemplate({
      cartCount: res
    }))
  })
}

updateCartCountRender()
// use xhr to add a product on the cart uploads flashbags and update cart Counter

$('.carousel .prev, .carousel .next').on('click', function () {
  let direction = $(this).hasClass('prev') ? 'prev' : 'next'
  changeThumb(direction)
})

$('.product').on('click', '.description .cart', function (event) {
  addProduct(event, $(this).attr('data-id'))
})

$('.actions').on('click', '.addToCart', function (event) {
  addProduct(event, $(this).attr('data-id'))
})

function addProduct (event, product) {
  event.preventDefault()
  let data = {}
  data.productId = product
  data.typeAction = 'add'
  patchProduct(data)
    .then(() => {
      updateCartCountRender()
      upCartBox(i18n.trans('cart.product.added'))
    })
}

// Logo Baseline

const body = document.querySelector('body')
const content = document.querySelector('.content')

content.onscroll = function () {
  content.scrollTop > 20
    ? body.classList.add('scrollTop')
    : body.classList.remove('scrollTop')
}
