import moment from 'moment'

const user_date_format_moment = 'MM-DD-YYYY'

export const formatDate = function(date, format=user_date_format_moment) {
    if(date.trim()=='') return date
    return moment(date).format(format)
}

export const humanReadableDate = function (string_date) {
    return moment(string_date).fromNow()
}

export const uuidv4 = function() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
      var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
      return v.toString(16);
    })
}

export const delay = ms => new Promise(res => setTimeout(res, ms))

/* export const loadComponent = function(path) {
    return import(path)
} */

/**
* recursively count the fields of a node
* 
* @param {object} node 
*/
export const getTotalNodeFields = (node) => {
    const nodeData = node.data
    const nodeKeys = Object.keys(nodeData)
    let total = 0
    nodeKeys.forEach(key => {
        const childNode = nodeData[key]
        if(!childNode.isContainer) total++
        total += getTotalNodeFields(childNode)
    })
    return total
 }

 /**
  * get a color based on a number
  * use with percentages
  * @param {number} value 
  */
 export const getColor = (value) => {
    //value from 0 to 1
    var hue=((1-value)*120).toString(10)
    return ["hsl(",hue,",100%,50%)"].join("")
}

/**
* recursively count the children of a node
* 
* @param {object} node 
*/
export const getTotalNodeChildren = (node) => {
    const nodeData = node.data
    const nodeKeys = Object.keys(nodeData)
    let total = nodeKeys.length
    nodeKeys.forEach(key => {
        const childNode = nodeData[key]
        total += getTotalNodeChildren(childNode)
    })
    return total
 }

 /**
  * recursively get the fields of a node
  * @param {object} node 
  */
 export const getNodeFields = (node) => {
     let fields = []
     if(node.isContainer) {
        const nodeData = node.data
        const nodeKeys = Object.keys(nodeData)
        nodeKeys.forEach(key => {
            const childNode = nodeData[key]
            const childFields = getNodeFields(childNode)
            fields = fields.concat(childFields)
        })
     }
     else fields.push(node.name)
     return fields
 }

/**
 * helper function to load scripts dynamically
 * 
 * @param {string} src 
 */ 
export const loadScript = function(src) {
    const promise = new Promise((resolve, reject) => {
        const scriptElement = document.createElement('script')
        scriptElement.src = src
        scriptElement.async = false // <-- this is important
        scriptElement.onload = function(e) {
            resolve(src)
        }
        scriptElement.onerror = function(e) {
            reject(src)
        }
        document.head.appendChild(scriptElement)
    })
    
    return promise
}
	
/**
 * helper function to load styles dynamically
 * 
 * @param {string} href 
 */
export const loadStyle = function(href) {
    const promise = new Promise((resolve, reject)=> {
        const styleElement = document.createElement('link')
        styleElement.rel = 'stylesheet'
        styleElement.type = 'text/css'
        styleElement.href = href
        styleElement.onload = function(e) {
            resolve(href)
        }
        styleElement.onerror = function(e) {
            reject(href)
        }
        document.head.appendChild(styleElement)
    })
    return promise
}

export const compare = function(obj1, obj2, type=null) {
    switch (type) {
        case 'date':
            /**
             * get rid of unwanted parts of the date (hours, minutes, seconds...)
             * it's needed when coparing dates to check if the fields are dirty
             */
            const regex = /(\d{4}-\d{2}-\d{2}).*/gi
            obj1 = obj1.replace(regex, '$1')
            obj2 = obj2.replace(regex, '$1')
            break
        default:
            break
    }
    return JSON.stringify(obj1) === JSON.stringify(obj2)
}