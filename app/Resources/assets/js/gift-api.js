import I18n from './i18n'

let i18n = new I18n()

export const postLogin = (data) => {
  return window.fetch('/login?_locale=' + i18n.guessLocale(), {
    headers: { 'Content-Type': 'application/json' },
    method: 'POST',
    credentials: 'include',
    body: JSON.stringify(data)
  })
    .then(res => {
      if (!res.ok) { res.json().then(res => { throw res.error }) }
      return res.json()
    })
}

export const resetLogin = (data) => {
  return window.fetch('/password-request?_locale=' + i18n.guessLocale(), {
    headers: { 'Content-Type': 'application/json' },
    method: 'POST',
    credentials: 'include',
    body: JSON.stringify(data)
  })
    .then(res => res.json())
    .then(res => {
      if (res.status !== 'ok') { throw res.message }
      return res.data
    })
}

export const postRegister = (data) => {
  return window.fetch('/register?_locale=' + i18n.guessLocale(), {
    headers: { 'Content-Type': 'application/json' },
    method: 'POST',
    credentials: 'include',
    body: JSON.stringify(data)
  })
    .catch(res => {
      console.error(res)
      throw (new Error('unknown_error'))
    })
    .then(res => res.json())
    .then(res => {
      if (res.status === 'ko') {
        throw res.message
      }
      return res
    })
}

export const postModify = (data) => {
  return window.fetch('/contact-update?_locale=' + i18n.guessLocale(), {
    headers: { 'Content-Type': 'application/json' },
    method: 'PUT',
    credentials: 'include',
    body: JSON.stringify(data)
  })
    .then(res => {
      if (!res.ok) { res.json().then(res => { throw res.error }) }
      return res.json()
    })
}

export const getLogout = (locale) => {
  return window.fetch('/logout?_locale=' + i18n.guessLocale(), {
    headers: { 'Content-Type': 'application/json' },
    method: 'GET',
    credentials: 'include'
  })
    .then(() => {
      window.location.replace('/' + locale + '/logout-message/don-ponctuel')
    })
}

export const getLogin = () => {
  return window.fetch('/login?_locale=' + i18n.guessLocale(), {
    method: 'GET',
    credentials: 'include'
  })
    .then(res => {
      if (!res.ok) { res.json().then(res => { throw res.message }) }
      return res.json()
    })
}

export const getCountryCode = (country, method) => {
  return window.fetch(`/xhr/tpays/code/${country}`, {
    method: 'GET',
    credentials: 'include'
  })
    .then(res => res.json())
    .then(res => {
      if (res.status !== 'ok') { throw res.error }
      return (method === 'CB') ? res.data['codpayspbx'] : res.data['codpayspaypal']
    })
}

export const postGift = (amount, allocation, method, memo, dateDebVir, dateFinVir, virPeriod) => {
  return window.fetch('/xhr/gift/create?_locale=' + i18n.guessLocale(), {
    headers: { 'Content-Type': 'application/json' },
    method: 'POST',
    credentials: 'include',
    body: JSON.stringify(
      {
        'gift': {
          'mntdon': amount,
          'destdon': allocation,
          'moddon': method,
          'memodon': memo,
          'dateDebVir': dateDebVir,
          'dateFinVir': dateFinVir,
          'virPeriod': virPeriod
        }
      }
    )
  })
    .then(res => res.json())
    .then(res => {
      if (res.status !== 'ok') { throw res.message }
      return res.data
    })
}
