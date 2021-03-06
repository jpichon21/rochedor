import $ from 'jquery'
import moment from 'moment'
import 'clndr'

const retreatsData = JSON.parse($('.retreats-data').html())
const translationsTitle = JSON.parse($('.translations-title').html())

/* Filters */

$(document).ready(function() {
  $('.filter').trigger('change')
})

$('.filter')

  .on('click', function () {
    if ($(this).hasClass('active')) {
      $(this).removeClass('active')
    } else {
      $('.filter').removeClass('active')
      $(this).addClass('active')
    }
  })

  .on('mouseleave', function () {
    $(this).removeClass('active')
  })

  .on('change', function () {
    const values = []
    $(this).find('input:checked').each(function (index) {
      values.push($(this).val())
    })
    const count = values.length
    $('.value .count', $(this)).text(count)
    $('.value div', $(this)).removeClass('active')
    if (count === 0) {
      $('.value .default', $(this)).addClass('active')
    } else if (count === 1) {
      $('.value .singular', $(this)).addClass('active')
    } else {
      $('.value .plural', $(this)).addClass('active')
    }
    updateFilters()
  })

/* Dates */

moment.locale('fr')

const lastRetreat = retreatsData[retreatsData.length - 1]
const dateLastRetreat = lastRetreat.dateOut

const dateBegin = moment().format('LL')
const dateEnd = moment(dateLastRetreat).format('LL')

$('.date-in span').text(dateBegin)
$('.date-out span').text(dateEnd)

$('.date-in').addClass('active')
$('.date-out').addClass('active')

$('.calendar-in').clndr({
  moment: moment,
  template: $('.calendar-template').html(),
  clickEvents: {
    click: function (target) {
      $('.date-in').addClass('active').find('span').text(moment(target.date._i).format('LL'))
      $('.date-in-value').val(moment(target.date._i).format('YYYYMMDD'))
      $('.calendar-in').removeClass('active')
      $('.filter.dates').removeClass('active')
      updateFilters()
    }
  }
})

$('.calendar-out').clndr({
  moment: moment,
  template: $('.calendar-template').html(),
  clickEvents: {
    click: function (target) {
      $('.date-out').addClass('active').find('span').text(moment(target.date._i).format('LL'))
      $('.date-out-value').val(moment(target.date._i).format('YYYYMMDD'))
      $('.calendar-out').removeClass('active')
      $('.filter.dates').removeClass('active')
      updateFilters()
    }
  }
})

$('.filter-dates').on('click', '.clndr-close', function () {
  $('.calendar-in').removeClass('active')
  $('.calendar-out').removeClass('active')
})

$('.date-in').on('click', function () {
  $('.calendar-in').addClass('active')
  $('.calendar-out').removeClass('active')
})

$('.date-out').on('click', function () {
  $('.calendar-out').addClass('active')
  $('.calendar-in').removeClass('active')
})

/* Table */

const retreatsTableTemplate = _.template($('.retreats-table-template').html())
const retreatsListTemplate = _.template($('.retreats-list-template').html())

function updateRetreats (data) {
  $('.content').stop().animate({ scrollTop: 0 }, 500, 'swing')
  $('.retreats-table tbody').html(retreatsTableTemplate({ retreats: data, translationsTitle: translationsTitle }))
  $('.retreats-list ul').html(retreatsListTemplate({ retreats: data }))
}

retreatsData.map(retreat => {
  retreat.dateIn = moment(retreat.dateIn)
  retreat.dateOut = moment(retreat.dateOut)
})

updateRetreats(retreatsData)

function applyFilters (filters) {
  const retreats = retreatsData.filter((retreat) => {
    if (filters['site'].length === 0) { return true }
    return filters['site'].indexOf(retreat.site.value) >= 0
  }).filter((retreat) => {
    if (filters['type'].length === 0) { return true }
    return filters['type'].indexOf(retreat.type.value.toString()) >= 0
  }).filter((retreat) => {
    if (filters['speaker'].length === 0) { return true }
    const speakers = retreat.speaker.filter(speaker => filters['speaker'].indexOf(speaker.value) >= 0)
    return speakers.length > 0
  }).filter((retreat) => {
    if (filters['translation'].length === 0) { return true }
    return retreat.translation.includes(filters['translation'])
  }).filter((retreat) => {
    if (filters['dateIn'].length === 0) { return true }
    return moment(retreat.dateIn).isSameOrAfter(filters['dateIn'])
  }).filter((retreat) => {
    if (filters['dateOut'].length === 0) { return true }
    return moment(retreat.dateOut).isSameOrBefore(filters['dateOut'])
  }).filter((retreat) => {
    if (filters['keywords'].length === 0) { return true }
    return stripAccents(retreat.event.toLowerCase()).includes(stripAccents(filters['keywords'].toLowerCase()))
  })
  updateRetreats(retreats)
}

function stripAccents (str) {
  return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
}

function updateFilters () {
  let filtersActived = {
    site: [],
    type: [],
    dateIn: '',
    dateOut: '',
    speaker: [],
    translation: [],
    keywords: ''
  }
  $('.filter').each(function () {
    const values = $(this).serializeArray()
    $.each(values, function (key, elmt) {
      filtersActived[elmt.name].push(elmt.value)
    })
  })
  const dates = $('.filter-dates').serializeArray()
  $.each(dates, function (key, elmt) {
    filtersActived[elmt.name] = elmt.value
  })
  const keywords = $('.filter-keywords').serializeArray()
  $.each(keywords, function (key, elmt) {
    filtersActived[elmt.name] = elmt.value
  })
  applyFilters(filtersActived)
}

/* RAZ */

$('.filter-raz').on('click', function (event) {
  event.preventDefault()
  $('.filters')
    .removeClass('active')
  $('.filter')
    .find('input:checked')
    .each(function () {
      $(this).prop('checked', false)
    })
    .trigger('change')
  $('.filter-dates')
    .find('input')
    .each(function () {
      $(this).val('')
    })
  $('.date-in')
    .removeClass('active')
    .find('span')
    .text(dateBegin)
  $('.date-out')
    .removeClass('active')
    .find('span')
    .text(dateEnd)
  $('.filter-keywords').find('input')
    .val('')
    updateFilters()
})

$('.filter-keywords').on('keyup', function (event) {
  event.preventDefault()
  updateFilters()
})

/* Animate Retreats Mobile */

$('.retreats-list').on('click', '.retreat', function () {
  $(this).toggleClass('active')
})
