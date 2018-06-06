import React from 'react'
import { connect } from 'react-redux'
import { withRouter } from 'react-router-dom'
import { getNews, putNews, deleteNews, setTitle, setLocale, initStatus } from '../../actions'
import NewsForm from '../news-form/news-form'
import AppMenu from '../app-menu/app-menu'
import Alert from '../alert/alert'
import { locales } from '../../locales'

export class NewsEdit extends React.Component {
  constructor (props) {
    super(props)
    this.state = {
      alertOpen: false,
      locales: locales
    }

    this.onSubmit = this.onSubmit.bind(this)
    this.onDelete = this.onDelete.bind(this)
    this.onVersionChange = this.onVersionChange.bind(this)
    this.onLocaleChange = this.onLocaleChange.bind(this)
    this.handleClose = this.handleClose.bind(this)
  }
  componentDidMount () {
    const { match: { params } } = this.props
    this.props.dispatch(getNews(params.newsId))
  }
  componentWillReceiveProps (nextProps) {
    this.props.dispatch(setTitle(`Modification de la news ${(nextProps.news) ? nextProps.news.title : ''}`))
    if ((nextProps.status !== 'ok' && nextProps.status !== '' && nextProps.status !== 'Deleted successfully') || nextProps.error) {
      this.setState({alertOpen: true})
    }

    console.log(nextProps)

    if (nextProps.status === 'Deleted successfully') {
      this.props.dispatch(initStatus)
      this.props.history.push('/news-list')
    }
  }
  onSubmit (news) {
    this.props.dispatch(putNews(news))
  }
  onVersionChange (news, version) {
    this.props.dispatch(getNews(news.id, version))
  }
  onLocaleChange (locale) {
    this.props.dispatch(setLocale(locale))
  }
  onDelete (news) {
    this.props.dispatch(deleteNews(news))
  }
  handleClose () {
    this.props.dispatch(initStatus())
    this.setState({ alertOpen: false })
    this.props.history.push('/news-list')
  }
  render () {
    return (
      <div>
        <Alert open={this.state.alertOpen} content={this.props.status} onClose={this.handleClose} />
        <AppMenu title={'Modification de la nouveauté'} localeHandler={this.onLocaleChange} locales={locales} locale={(this.props.news) ? this.props.news.locale : 'fr'} />
        <NewsForm news={this.props.news} submitHandler={this.onSubmit} deleteHandler={this.onDelete} versionHandler={this.onVersionChange} edit />
      </div>
    )
  }
}

const mapStateToProps = state => {
  return {
    loading: state.loading,
    news: state.news,
    status: state.status,
    error: state.error
  }
}

NewsEdit.defaultProps = {
  news: {
    id: null,
    title: '',
    sub_title: '',
    url: '',
    description: '',
    locale: 'fr'
  },
  status: ''
}

export default withRouter(connect(mapStateToProps)(NewsEdit))