import moment from 'moment'
import {date_format} from '@/variables'

const download = (filename, text) => {
  const url = window.URL.createObjectURL(new Blob([text]))
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', filename)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

const formatDate = (date) => {
  if(!date) return ''
  const date_string = moment(date).format(date_format) // date_format defined in variables
  return date_string
}

  export {download, formatDate}