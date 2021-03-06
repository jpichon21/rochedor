import React from 'react'
import { connect } from 'react-redux'
import { withRouter } from 'react-router-dom'
import { getPage, putPage, deletePage, setTitle, setLocale, initStatus, getPageTranslations } from '../../actions'
import PageForm from '../page-form/page-form'
import AppMenu from '../app-menu/app-menu'
import Alert from '../alert/alert'
import { locales } from '../../locales'
import update from 'immutability-helper'
import { Snackbar, Button } from '@material-ui/core'
import IsAuthorized, { ACTION_PAGE_EDIT } from '../../isauthorized/isauthorized'
import Redirect from 'react-router/Redirect'

export class PageEdit extends React.Component {
  constructor (props) {
    super(props)
    this.state = {
      alertOpen: false,
      locales: locales,
      snackbarOpen: false,
      snackbarContent: ''
    }
    this.onSubmit = this.onSubmit.bind(this)
    this.onDelete = this.onDelete.bind(this)
    this.onVersionChange = this.onVersionChange.bind(this)
    this.onLocaleChange = this.onLocaleChange.bind(this)
    this.handleClose = this.handleClose.bind(this)
    this.handleCloseSnack = this.handleCloseSnack.bind(this)
  }

  componentDidMount () {
    const { match: { params } } = this.props
    this.props.dispatch(getPage(params.pageId))
    this.props.dispatch(getPageTranslations(params.pageId))
  }

  componentWillReceiveProps (nextProps) {
    this.props.dispatch(setTitle(`Modification d'une page`))
    if ((nextProps.status !== 'ok' && nextProps.status !== '' && nextProps.status !== 'Deleted successfully' && nextProps.status !== 'Page updated') || nextProps.error) {
      this.setState({ alertOpen: true })
    }
    if (nextProps.page !== null && this.props.page !== null) {
      if (nextProps.page.id !== this.props.page.id) {
        this.props.dispatch(getPageTranslations(nextProps.page.id))
      }
    }
    if (nextProps.translations) {
      const ts = nextProps.translations
      let l = { 'fr': 'Français' }
      for (let k in ts) {
        l = update(l, {
          [ts[k]['locale']]: {
            $set: locales[ts[k]['locale']]
          }
        })
      }
      this.setState({ locales: l })
    }
  }

  onSubmit (page) {
    this.props.dispatch(putPage(page)).then((res) => {
      this.setState({ snackbarContent: 'Page enregistrée et publiée', snackbarOpen: true })
      this.props.dispatch(getPage(page.id))
    })
  }

  onVersionChange (page, version) {
    this.props.dispatch(getPage(page.id, version))
  }

  onLocaleChange (locale) {
    if (locale === 'fr') {
      this.props.dispatch(getPage(this.props.page.parent.id))
      this.props.history.push(`/page-edit/${this.props.page.parent.id}`)
    } else {
      const ts = this.props.translations
      for (let k in ts) {
        if (ts[k].locale === locale) {
          this.props.dispatch(getPage(ts[k].id))
          this.props.history.push(`/page-edit/${ts[k].id}`)
        }
      }
    }
    this.props.dispatch(setLocale(locale))
  }
  onDelete (page) {
    this.props.dispatch(deletePage(page)).then((res) => {
      this.setState({ snackbarOpen: true, snackbarContent: 'Page supprimée' })
      setTimeout(() => {
        this.props.history.push(`/page-list`)
      }, 500)
    })
  }

  handleClose () {
    this.props.dispatch(initStatus())
    this.setState({ alertOpen: false })
  }

  handleCloseSnack () {
    this.setState({ snackbarOpen: false, snackbarContent: '' })
  }

  render () {
    return (
      <IsAuthorized action={ACTION_PAGE_EDIT} alternative={<Redirect to={'/page-list'} />}>
        <div>
          <Alert open={this.state.alertOpen} content={this.props.status} onClose={this.handleClose} />
          <AppMenu goBack='/page-list' title={`Modification d'une page`} localeHandler={this.onLocaleChange} locales={this.state.locales} locale={this.props.page.locale} />
          <Snackbar
            open={this.state.snackbarOpen}
            autoHideDuration={4000}
            onClose={this.handleCloseSnack}
            ContentProps={{
              'aria-describedby': 'snackbar-fab-message-id'
            }}
            message={<span id='snackbar-fab-message-id'>{this.state.snackbarContent}</span>}
            action={
              <Button color='inherit' size='small' onClick={this.handleCloseSnack}>
                Ok
              </Button>
            }
          />
          <PageForm page={this.props.page} submitHandler={this.onSubmit} deleteHandler={this.onDelete} versionHandler={this.onVersionChange} edit translations={this.props.translations} />
        </div>
      </IsAuthorized>
    )
  }
}

const mapStateToProps = state => {
  return {
    loading: state.loading,
    page: state.page,
    status: state.status,
    error: state.error,
    translations: state.pageTranslations
  }
}

PageEdit.defaultProps = {
  parents: {},
  parentKey: 0,
  page: {
    locale: 'fr',
    title: '',
    sub_title: '',
    url: '',
    description: '',
    parent_id: null,
    content: {
      intro: '',
      intro_line2: '',
      intro_line3: '',
      sections: [
        {
          title: '',
          body: '',
          slides: [
            {
              layout: '1-1-2',
              images: [
                { type: '', url: '', alt: '', video: '', crop: '' },
                { type: '', url: '', alt: '', video: '', crop: '' },
                { type: '', url: '', alt: '', video: '', crop: '' },
                { type: '', url: '', alt: '', video: '', crop: '' }
              ]
            }
          ]
        }
      ]
    }
  }
}

export default withRouter(connect(mapStateToProps)(PageEdit))
