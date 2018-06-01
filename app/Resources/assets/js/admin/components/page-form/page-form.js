import React from 'react'
import { compose } from 'redux'
import PropTypes from 'prop-types'
import { connect } from 'react-redux'
import { withRouter } from 'react-router-dom'
import { MenuItem, Menu, GridList, GridListTile, TextField, Button, Typography, Grid, ExpansionPanel, ExpansionPanelDetails, ExpansionPanelSummary, ExpansionPanelActions, Divider, Select } from '@material-ui/core'
import ExpandMoreIcon from '@material-ui/icons/ExpandMore'
import WrapTextIcon from '@material-ui/icons/WrapText'
import SaveIcon from '@material-ui/icons/Save'
import { withStyles } from '@material-ui/core/styles'
import { getPages } from '../../actions'
import RichEditor from './RichEditor'
import { tileData } from './tileData'

export class PageForm extends React.Component {
  constructor (props) {
    super(props)
    this.state = {
      locale: this.props.lang,
      page: this.props.page,
      versionCount: 0,
      submitDisabled: true,
      anchorMenuLayout: null,
      menuLayoutOpened: false,
      layout: '1-1-2'
    }
    this.handleInputChange = this.handleInputChange.bind(this)
    this.handleInputFilter = this.handleInputFilter.bind(this)
    this.handleSubmit = this.handleSubmit.bind(this)
    this.handleLocaleChange = this.handleLocaleChange.bind(this)
    this.handleLayoutMenu = this.handleLayoutMenu.bind(this)
    this.handleCloseLayoutMenu = this.handleCloseLayoutMenu.bind(this)
    this.handleChangeLayoutMenu = this.handleChangeLayoutMenu.bind(this)
    this.handleVersion = this.handleVersion.bind(this)
  }

  handleLocaleChange (event) {
    this.setState({ locale: event.target.value }, () => {
      this.props.dispatch(getPages(this.state.locale))
    })
  }

  handleVersion (event) {
    this.setState({ versionCount: event.target.value })
    this.props.versionHandler(this.state.page, this.props.versions[event.target.value].version)
    event.preventDefault()
  }

  handleInputChange (event) {
    const value = event.target.value
    const name = event.target.name
    this.setState(prevState => {
      return {
        page: {
          ...prevState.page,
          [name]: value
        }
      }
    }, () => {
      this.setState({ submitDisabled: (this.state.page.title === '' || this.state.page.description === '') })
    })
  }

  handleSubmit (event) {
    event.preventDefault()
    this.props.submitHandler(this.state.page)
  }

  handleInputFilter (event) {
    const re = /[0-9A-Za-z-]+/g
    if (!re.test(event.key)) {
      event.preventDefault()
    }
  }

  handleLayoutMenu (event) {
    this.setState({ anchorMenuLayout: event.currentTarget })
  }

  handleCloseLayoutMenu () {
    this.setState({ anchorMenuLayout: null })
  }

  handleChangeLayoutMenu (event) {
    this.setState({
      layout: event.target.getAttribute('layout'),
      anchorMenuLayout: null
    })
  }

  componentWillReceiveProps (nextProps) {
    if (nextProps.page) {
      this.setState({ page: nextProps.page })
    }
  }

  render () {
    const { classes } = this.props
    const { anchorMenuLayout } = this.state
    const menuLayoutOpened = Boolean(anchorMenuLayout)
    const versions = (this.props.versions.length > 0)
      ? this.props.versions.map((v, k) => {
        return (
          <MenuItem value={k} key={v.id}>{v.logged_at}</MenuItem>
        )
      })
      : null
    return (
      <div className={classes.container}>
        {
          this.props.edit
            ? (
              <Select
                className={classes.option}
                value={this.state.versionCount}
                onChange={this.handleVersion}
                inputProps={{
                  name: 'historique',
                  id: 'version'
                }}>
                {versions}
              </Select>
            )
            : (
              ''
            )
        }
        <Typography variant='display1' className={classes.title}>
          SEO
        </Typography>
        <form className={classes.form}>
          <TextField
            required
            autoComplete='off'
            InputLabelProps={{ shrink: true }}
            className={classes.textfield}
            fullWidth
            id='title'
            name='title'
            label='Titre ligne 1'
            value={this.state.page.title}
            onChange={this.handleInputChange} />
          <TextField
            autoComplete='off'
            InputLabelProps={{ shrink: true }}
            className={classes.textfield}
            fullWidth
            id='sub_title'
            name='sub_title'
            label='Titre ligne 2'
            value={this.state.page.sub_title}
            onChange={this.handleInputChange} />
          <TextField
            autoComplete='off'
            InputLabelProps={{ shrink: true }}
            className={classes.textfield}
            fullWidth
            id='url'
            name='url'
            label='Url'
            value={this.state.page.url}
            onChange={this.handleInputChange}
            onKeyPress={this.handleInputFilter} />
          <TextField
            required
            autoComplete='off'
            InputLabelProps={{ shrink: true }}
            className={classes.textfield}
            fullWidth
            multiline
            id='description'
            name='description'
            label='Meta-description'
            value={this.state.page.description}
            onChange={this.handleInputChange} />
        </form>
        <Typography variant='display1' className={classes.title}>
          Contenu
        </Typography>
        <form className={classes.form}>
          <TextField
            autoComplete='off'
            InputLabelProps={{ shrink: true }}
            className={classes.textfield}
            fullWidth
            multiline
            id='intro'
            name='intro'
            label='Introduction'
            value={this.state.page.content.intro}
            onChange={this.handleInputChange} />
          <ExpansionPanel className={classes.expansion}>
            <ExpansionPanelSummary expandIcon={<ExpandMoreIcon />}>
              <Typography>
                Volet 1
              </Typography>
            </ExpansionPanelSummary>
            <ExpansionPanelDetails className={classes.details}>
              <Grid container spacing={32}>
                <Grid item xs={6}>
                  <RichEditor />
                </Grid>
                <Grid item xs={6}>
                  <GridList className={classes.gridList} cols={2} rows={2}>
                    {
                      tileData[this.state.layout].map(tile => (
                        <GridListTile key={tile.id} cols={tile.cols} rows={tile.rows}>
                          <img src={tile.img} alt={tile.title} />
                        </GridListTile>
                      ))
                    }
                  </GridList>
                  <div className={classes.options}>
                    <Button
                      variant='outlined'
                      aria-owns={menuLayoutOpened ? 'layout-menu' : null}
                      aria-haspopup='true'
                      onClick={this.handleLayoutMenu}
                      className={classes.option}>
                      Disposition
                    </Button>
                    <Button variant='outlined' disabled className={classes.option}>Supprimer</Button>
                    <Button variant='outlined' color='primary' className={classes.option}>Ajouter</Button>
                  </div>
                  <Menu
                    id='layout-menu'
                    anchorEl={anchorMenuLayout}
                    anchorOrigin={{
                      vertical: 'top',
                      horizontal: 'right'
                    }}
                    transformOrigin={{
                      vertical: 'top',
                      horizontal: 'right'
                    }}
                    open={menuLayoutOpened}
                    onClose={this.handleCloseLayoutMenu}>
                    <MenuItem onClick={this.handleChangeLayoutMenu} layout={'2'}>1 Image</MenuItem>
                    <MenuItem onClick={this.handleChangeLayoutMenu} layout={'2-2'}>2 Images horizontales</MenuItem>
                    <MenuItem onClick={this.handleChangeLayoutMenu} layout={'1-1'}>2 Images verticales</MenuItem>
                    <MenuItem onClick={this.handleChangeLayoutMenu} layout={'2-1-1'}>3 Images (Horizontale en haut)</MenuItem>
                    <MenuItem onClick={this.handleChangeLayoutMenu} layout={'1-1-2'}>3 Images (Horizontale en bas)</MenuItem>
                    <MenuItem onClick={this.handleChangeLayoutMenu} layout={'1-1-1-1'}>4 Images</MenuItem>
                  </Menu>
                </Grid>
              </Grid>
            </ExpansionPanelDetails>
            <Divider />
            <ExpansionPanelActions>
              <Button disabled>Supprimer</Button>
            </ExpansionPanelActions>
          </ExpansionPanel>
        </form>
        <div className={classes.buttons}>
          <Button
            className={classes.button}
            variant='fab'
            color='primary'>
            <WrapTextIcon />
          </Button>
          <Button
            disabled={this.state.submitDisabled}
            onClick={this.handleSubmit}
            className={classes.button}
            variant='fab'
            color='secondary'>
            <SaveIcon />
          </Button>
        </div>
      </div>
    )
  }
}

const styles = theme => ({
  ...theme,
  paper: {
    padding: theme.myMarge
  },
  options: {
    marginTop: theme.myMarge,
    textAlign: 'center'
  },
  option: {
    marginRight: theme.myMarge / 3,
    marginLeft: theme.myMarge / 3
  },
  expansion: {
    marginBottom: theme.myMarge
  }
})

const mapStateToProps = state => {
  return {
    status: state.postPageStatus,
    versions: state.pageVersions,
    version: state.pageVersion
  }
}

PageForm.propTypes = {
  classes: PropTypes.object.isRequired
}

export default withRouter(compose(withStyles(styles), connect(mapStateToProps))(PageForm))
