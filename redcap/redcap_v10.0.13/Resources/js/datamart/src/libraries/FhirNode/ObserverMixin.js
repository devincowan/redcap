const ObserverMixin = () => ({
    observers: [],
    /**
     * add an observer
     * @param {object} observer 
     * @returns Integer the id of the observer (for removal)
     */
    addObserver(observer)
    {
        if(this.observers.indexOf(observer)>=0) return
        this.observers.push(observer)
    },

    /**
     * 
     * @param {*} observer 
     */
    removeObserver(observer)
    {
        const observer_index = this.observers.indexOf(observer)
        this.observers.splice(observer_index, 1)
    },


    /**
     * notify allobservers
     * @param {Mixed} params 
     */
    notifyObservers(params=null)
    {
        this.observers.forEach(observer => {
            try {
                observer.update(params)
            } catch (error) {
                console.log('error notifying the observer',observer,  error)
            }
        })
    },
})

export default ObserverMixin