import $ from 'jquery'

$(document).ready(function () {
  $('.menu a, .yellow, .black').click(function (event) {
    var $this = this
    event.preventDefault()
    var newLocation = $this.href
    $('body').fadeOut(1000, function () {
      $(this).addClass('no-transition')
      newpage(newLocation)
    })

    function newpage (newLocation) {
      window.location = newLocation
    }
  })
})
