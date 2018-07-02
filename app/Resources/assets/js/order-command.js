import $ from 'jquery'
import moment from 'moment'
import { getParticipant, getDelivery } from './sample'
import { placePayment } from './cart.js'
import {
  postParticipant,
  getLogin,
  getLogout,
  getRegistered,
  getData,
  postLogin,
  postOrder
} from './order-api.js'

const _infos = JSON.parse($('.infos-json').html())

/* Translations */

const _translations = JSON.parse($('.translations-json').html())

moment.locale(_translations.locale)

/* Dropdowns */

function changeItem (elmt) {
  $('.dropdown .item').each(function () {
    this.style.maxHeight = null
    this.classList.remove('active')
  })
  elmt[0].classList.add('active')
  elmt[0].style.maxHeight = elmt[0].scrollHeight + 'px'
}

$(document).ready(function () {
  setTimeout(function () {
    changeItem($('.dropdown .item:first'))
  }, 500)
})

/* Button Radio */

$('.registered-render').on('click', '.button.radio', function (event) {
  event.preventDefault()
  $(this).toggleClass('checked')
})

$('.item-participants').on('click', '.button.radio', function (event) {
  event.preventDefault()
  $(this).toggleClass('checked')
})

/* Variables */

let _you = {}
let _registered = []
let _delivery = {}
let _cartInfo = {}
let _participant = {}
let _participants = []

const itemConnection = $('.item.connection')
const itemOrder = $('.item.order')
const itemValidation = $('.item.validation')
const itemCart = $('.item.cart')
const itemPayment = $('.item.payment')

/* Renders */

const youTemplate = _.template($('.you-template').html())
const deliveryFormTemplate = _.template($('.delivery-form-template').html())
const registeredTemplate = _.template($('.registered-template').html())
const cartTemplate = _.template($('.cart-template').html())
const youFormTemplate = _.template($('.you-form-template').html())
const himFormTemplate = _.template($('.him-form-template').html())

function updateYouRender () {
  $('.you-render').html(youTemplate({ you: _you }))
}

function updateRegisteredRender () {
  $('.registered-render').html(registeredTemplate({ registered: _registered }))
}

function updateCartRender () {
  $('.participants-render').html(cartTemplate({ participants: _delivery, translations: _translations, cartInfo: _cartInfo }))
}

function updateYouFormRender () {
  $('.you-form-render').html(youFormTemplate({ participant: _participant }))
}

function updateHimFormRender () {
  $('.him-form-render').html(himFormTemplate({ participant: _participant, registered: _registered, you: _you }))
}

function updateDeliveryFormRender () {
  $('.delivery-form-render').html(deliveryFormTemplate({ delivery: _delivery, registered: _registered, you: _you }))
}

updateCartRender()

/* Actions */
itemOrder.on('change', '.select.adliv', function (event) {
  event.preventDefault()
  const data = $(this).val()
  let selectedVal = data
  _delivery.destliv = data
  if (selectedVal === 'Roche' || selectedVal === 'Font' || selectedVal === 'myAdd') {
    _delivery.adliv.adresse = _you.adresse
    _delivery.adliv.zipcode = _you.cp
    _delivery.adliv.city = _you.ville
    $(`.panel.adliv`, itemOrder).removeClass('active')
  } else {
    $(`.panel.adliv`, itemOrder).addClass('active')
  }
  if (selectedVal === 'myAdd' || selectedVal === 'Other') {
    $(`.panel.countryliv`, itemOrder).addClass('active')
  } else {
    $(`.panel.countryliv`, itemOrder).removeClass('active')
  }
  if (selectedVal === 'Roche' || selectedVal === 'Font') {
    _delivery.paysliv = 'FR'
  }
  changeItem(itemOrder)
})

itemPayment.on('click', '.button.submit.process-order', function (event) {
  event.preventDefault()
  postOrder(_delivery).then(res => {
    console.log('Coucou avant')
    let result = $('.result', itemValidation).html()
    result = result.replace('%entry_number%', res)
    $('.result', itemValidation).html(result)
    placePayment(_delivery.modpaie, _cartInfo.priceIT, '18-00015', 'truc', `Commande sur le site La Roche D'Or`, _you.email, 'fr')
    changeItem(itemValidation)
    console.log('Coucou apres')
  }).catch(error => {
    $('.right .catch-message').html(error)
  })
})

itemOrder.on('change', '.select.country', function (event) {
  event.preventDefault()
  const data = $(this).val()
  _delivery.paysliv = data
  console.log(_delivery)
})

itemPayment.on('change', '.select.modpaie', function (event) {
  event.preventDefault()
  const data = $(this).val()
  _delivery.modpaie = data
  console.log(data)
  console.log(_delivery)
})

itemOrder.on('submit', '.panel.adliv form', function (event) {
  event.preventDefault()
  const data = $(this).serializeArray()
  let dataVal = data
  _delivery.adliv.adresse = dataVal[0].value
  _delivery.adliv.zipcode = dataVal[1].value
  _delivery.adliv.city = dataVal[2].value
  console.log(_delivery)
})

itemOrder.on('submit', '.panel.adliv form', function (event) {
  event.preventDefault()
  const data = $(this).serializeArray()
  let dataVal = data
  _delivery.adliv.adresse = dataVal[0].value
  _delivery.adliv.zipcode = dataVal[1].value
  _delivery.adliv.city = dataVal[2].value
  console.log(_delivery)
})

itemOrder.on('click', '.button.payment', function (event) {
  event.preventDefault()
  if (_delivery.adliv.adresse === '' ||
    _delivery.adliv.zipcode === '' ||
    _delivery.adliv.city === '' ||
    _delivery.destliv === '' ||
    _delivery.country === '') {
    console.log('error')
  } else {
    getData(_delivery.cartId, _delivery.destliv, _delivery.paysliv).then(data => {
      console.log(data)
      _cartInfo = data
      updateCartRender()
      changeItem(itemPayment)
    }).catch(error => {
      $('.right .catch-message').html(error)
    })
  }
})

itemOrder.on('submit', '.form.gift', function (event) {
  event.preventDefault()
  const data = $(this).serializeArray()
  let dataVal = data
  _delivery.memocmd = dataVal[0].value
  console.log(_delivery)
})

itemOrder.on('click', 'a', function (event) {
  event.preventDefault()
  $('a', itemOrder).removeClass('active')
  $(this).addClass('active')
  const which = $(this).attr('href').substring(1)
  switch (which) {
    case 'connection':
    case 'registration':
      $('.panel', itemOrder).hide()
      $(`.panel.${which}`, itemOrder).show()
      _participant = getParticipant()
      updateYouFormRender()
      break
    case 'continue':
      getLogin().then(user => afterLogin(user))
      break
    case 'disconnect':
      getLogout()
      break
  }
  changeItem(itemPayment)
})

itemPayment.on('click', 'a', function (event) {
  event.preventDefault()
  $('a', itemPayment).removeClass('active')
  $(this).addClass('active')
  const which = $(this).attr('href').substring(1)
  switch (which) {
    case 'connection':
    case 'registration':
      $('.panel', itemPayment).hide()
      $(`.panel.${which}`, itemPayment).show()
      _participant = getParticipant()
      updateYouFormRender()
      break
    case 'continue':
      getLogin().then(user => afterLogin(user))
      break
    case 'disconnect':
      getLogout()
      break
  }
  changeItem(itemOrder)
})

itemCart.on('click', 'a', function (event) {
  event.preventDefault()
  $('a', itemCart).removeClass('active')
  $(this).addClass('active')
  const which = $(this).attr('href').substring(1)
  switch (which) {
    case 'connection':
    case 'registration':
      $('.item.cart', itemCart).hide()
      $(`.panel.${which}`, itemCart).show()
      _participant = getParticipant()
      updateYouFormRender()
      break
    case 'continue':
      getLogin().then(user => afterLogin(user))
      break
    case 'disconnect':
      getLogout()
      break
  }
  changeItem(itemConnection)
})

function afterLogin (user) {
  const delivery = getDelivery()
  _delivery = delivery
  console.log(_delivery)
  const participant = getParticipant()
  _you = { ...participant, ...user }
  _participants = [_you]
  updateYouRender()
  getRegistered().then(registered => {
    _registered = registered.map(obj => {
      return { ...participant, ...obj }
    })
    updateRegisteredRender()
    updateCartRender()
    changeItem(itemOrder)
  })
}

function formatParticipant (data) {
  let participant = getParticipant()
  data.map(obj => {
    participant[obj.name] = obj.value
  })
  participant.codco = parseInt(participant.codco)
  participant.datnaiss = moment(participant.datnaiss, 'DD/MM/YYYY').format()
  return participant
}

itemConnection.on('submit', '.panel.connection form', function (event) {
  event.preventDefault()
  postLogin({
    username: $('.username', this).val(),
    password: $('.password', this).val()
  }).then(user => {
    afterLogin(user)
  })
})

itemConnection.on('submit', '.panel.registration form', function (event) {
  event.preventDefault()
  const data = $(this).serializeArray()
  const participant = formatParticipant(data)
  const validate = validateDate(participant.datnaiss)
  if (validate) {
    postRegister({
      contact: participant
    }).then(user => {
      postLogin({
        username: user.username,
        password: participant.password
      }).then(user => {
        afterLogin(user)
      })
    }).catch(error => {
      $('.catch-message', itemConnection).html(error)
    })
  } else {
    $('.catch-message', itemConnection).html(_translations.message.date_invalid)
  }
})

itemConnection.on('click', 'a', function (event) {
  event.preventDefault()
  $('a', itemConnection).removeClass('active')
  $(this).addClass('active')
  const which = $(this).attr('href').substring(1)
  switch (which) {
    case 'connection':
    case 'registration':
      $('.panel', itemConnection).hide()
      $(`.panel.${which}`, itemConnection).show()
      _participant = getParticipant()
      updateYouFormRender()
      break
    case 'continue':
      getLogin().then(user => afterLogin(user))
      break
    case 'disconnect':
      getLogout()
      break
  }
  changeItem(itemConnection)
})

function validateDate (date) {
  return moment(date).isValid()
}

function validateParticipant (participant) {
  if (validateDate(participant.datnaiss)) {
    if (moment().diff(moment(participant.datnaiss), 'years') >= 16) {
      return { success: true }
    } else {
      if (participant.coltyp === 'enfan') {
        const people = [..._registered, _you]
        const filtered = people.filter(person => {
          return person.codco === parseInt(participant.colp)
        })
        const parent = filtered.shift()
        if (moment().diff(moment(parent.datnaiss), 'years') >= 18) {
          return { success: true }
        } else {
          return { error: _translations.message.parent_must_be_adult }
        }
      } else {
        return { error: _translations.message.must_be_a_child }
      }
    }
  } else {
    return { error: _translations.message.date_invalid }
  }
}

function callbackSubmit (event, context, action, callback) {
  event.preventDefault()
  const data = context.serializeArray()
  const participant = formatParticipant(data)
  const validate = validateParticipant(participant)
  if (validate.success) {
    postParticipant(participant).then(res => {
      const participantUpdated = { ...participant, ...res }
      callback(participantUpdated)
      updateYouRender()
      updateRegisteredRender()
      updateParticipants()
      $('.right .catch-message').html('')
      $(`.panel.${action}`).slideUp(800, function () {
        $(this).hide()
        changeItem(itemOrder)
      })
    })
  } else {
    $('.catch-message', itemOrder).html(validate.error)
  }
}

itemOrder.on('submit', '.panel.you form', function (event) {
  callbackSubmit(event, $(this), 'you', function (res) {
    _you = res
  })
})

itemOrder.on('submit', '.panel.modify form', function (event) {
  callbackSubmit(event, $(this), 'modify', function (res) {
    _registered = _registered.map(obj => {
      if (obj.codco === res.codco) { return res }
      return obj
    })
  })
})

itemOrder.on('submit', '.panel.add form', function (event) {
  callbackSubmit(event, $(this), 'add', function (res) {
    _registered.push(res)
  })
})

function updateParticipants () {
  console.log(_registered)
  _participants = _registered.filter(participant => {
    console.log(participant)
    return participant.check
  })
  _participants.push(_you)
  updateCartRender()
  updateDeliveryFormRender()
}

itemOrder.on('click', '.participate-him', function (event) {
  event.preventDefault()
  const id = parseInt($(this).attr('data-id'))
  _registered = _registered.map(participant => {
    if (participant.codco === id) {
      participant.check = !participant.check
    }
    return participant
  })
  updateParticipants()
})

itemOrder.on('click', '.modify-you', function (event) {
  event.preventDefault()
  _participant = _you
  $('.panel', itemOrder).hide()
  $(`.panel.you`, itemOrder).show()
  updateYouFormRender()
  changeItem(itemOrder)
})

function validateTransports () {
  let validate = true
  _participants.map(participant => {
    if (participant.transport === '') { validate = false }
  })
  return validate
}

itemOrder.on('click', '.validate-participants', function (event) {
  event.preventDefault()
  const validate = validateTransports()
  if (validate) {
    postRegistered(_participants, _infos.idact).then(res => {
      let result = $('.result', itemValidation).html()
      result = result.replace('%entry_number%', res)
      $('.result', itemValidation).html(result)
      changeItem(itemValidation)
    }).catch(error => {
      $('.right .catch-message').html(error)
    })
  } else {
    $('.right .catch-message').html(_translations.message.verify_transport)
  }
})