import React from 'react'
import { connect } from 'react-redux'
import { initStatus, setLocale } from '../../actions'
import SpeakerForm from '../speaker-form/speaker-form'
import AppMenu from '../app-menu/app-menu'
import { locales } from '../../locales'
import Alert from '../alert/alert'
import IsAuthorized, { ACTION_SPEAKER_CREATE } from '../../isauthorized/isauthorized'
import Redirect from 'react-router-dom/Redirect'

export class SpeakerCreate extends React.Component {
  constructor (props) {
    super(props)
    this.state = {
      alertOpen: false
    }
    this.onLocaleChange = this.onLocaleChange.bind(this)
    this.handleClose = this.handleClose.bind(this)
  }

  componentWillMount () {
    this.props.dispatch(initStatus())
  }

  onLocaleChange (locale) {
    this.props.dispatch(setLocale(locale))
  }
  handleClose () {
    this.props.dispatch(initStatus())
    this.setState({ alertOpen: false })
  }
  render () {
    return (
      <div>
        <IsAuthorized action={ACTION_SPEAKER_CREATE} alternative={<Redirect to='/speaker-list' />}>
          <Alert open={this.state.alertOpen} content={this.props.status} onClose={this.handleClose} />
          <AppMenu title={'Ajout d\'un intervenant'} localeHandler={this.onLocaleChange} locales={locales} locale={this.props.locale} />
          <SpeakerForm locale={this.props.locale} />
        </IsAuthorized>
      </div>
    )
  }
}

const mapStateToProps = state => {
  return {
    status: state.status,
    error: state.error,
    locale: state.locale
  }
}

export default connect(mapStateToProps)(SpeakerCreate)
