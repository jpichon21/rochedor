const Nightmare = require('nightmare')
const addContext = require('mochawesome/addContext')
const sinon = require('sinon')
const request = require('request')
const chai = require('chai')
const should = chai.should()

const imgDir = './tests/reports/screenshots/inscription'

describe('Load Page', function () {
  this.timeout('30s')
  let nightmare = null
  beforeEach(() => {
    nightmare = new Nightmare()
  })
  describe('L\'inscription doit se dérouler sans erreur', () => {
    it('Les actions suivantes doivent se derouler sans timout', done => {
      addContext(this, `./screenshots/inscription/Initial-Page.png`)
      nightmare
        .goto('http://127.0.0.1:8001/inscription-retraite?id=3176')
      // .screenshot(`${imgDir}button-yes-no.png`)
        .wait('.item.connection .button.yes')
        .click('.item.connection .button.yes')
        .wait('.panel.connection')
      // .screenshot(`${imgDir}login-form.png`)
        .type('.panel.connection .input.username', 'admin')
        .type('.panel.connection .input.password', 'admin')
        .click('.panel.connection .button.submit')
        .wait('.item.participants.active')
        // .screenshot(`${imgDir}recap-participants.png`)
        .click('.item.participants.active .add-participant')
        .wait('.panel.add')
        // .screenshot(`${imgDir}form-participants.png`)
        .select('.select.colt', 'conjo')
        .select('.select.colp', 'John')
        .select('.select.civil', 'Mme')
        .type('.input.prenom', 'Helene')
        .type('.input.nom', 'Doooooe')
        .type('.textarea.adresse', '8 rue des passants')
        .type('.input.cp', '25000')
        .type('.input.ville', 'Besançon')
        .type('.input.pays', 'France')
        .type('.input.tel', '038114578')
        .type('.input.mobil', '038114578')
        .type('.input.email', 'zmail@zmail.com')
        .type('.input.datnaiss', '24/09/1995')
        .type('.input.profession', 'Garagiste')
        .select('.select.transport', 'perso')
        .type('.textarea.memo', 'Diabétique végétarien')
        .click('.input.button.submit')
        .wait('.participate-him.button.radio')
        // .screenshot(`${imgDir}recap-feed-participants.png`)
        .click('.participate-him.button.radio')
        .click('.validate-participants.button')
        // .wait('.item.validation.active')
        // .wait('.item.validation.active .validate')
        .end()
        .then(function (result) { done() })
        .catch(done)
    })
    // describe('L\'email est envoyer', () => {
    //   it('Il s\'agit du bonne email', (done) => {
    //     request('http://127.0.0.1:8080/api/emails', function (err, res, body) {
    //       res.statusCode.should.eql(200)
    //       res.headers['content-type'].should.contain('application/json')
    //       body = JSON.parse(body)
    //       body[0].subject.should.eql('Demande d\'inscription')
    //       body[0].headers.from.should.eql('Tristan Cuenot <webmaster@logomotion.fr>')
    //       done()
    //     })
    //   })
    // })
  })
})
