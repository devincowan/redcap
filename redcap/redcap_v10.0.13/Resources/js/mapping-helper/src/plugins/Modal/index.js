// import Modal from './Modal'

import ModalContainer from './ModalContainer'

export default {
    /**
     * 
     * @param {object} Vue 
     * @param {object} options options will be used to pass arguments to the modal container. i.e. store, router
     */
    install(Vue, options) {
        window.addEventListener('DOMContentLoaded', (event) => { 
            let container = document.querySelector('[data-modal]')
            if(!container) {
                container = document.createElement('div')
                container.setAttribute('data-modal', true)
                document.body.appendChild(container)
            }

            const Constructor = Vue.extend(ModalContainer)
            const modalComponent = new Constructor(options).$mount(container) // pass the options to the modal container
            // console.log(Constructor)
            // create a modal instance
            // set a global $API reference with params
            Vue.$RcModal = modalComponent
            // Add Vue instance methods by attaching them to Vue.prototype.
            Vue.prototype.$RcModal = modalComponent
        })
    },
}