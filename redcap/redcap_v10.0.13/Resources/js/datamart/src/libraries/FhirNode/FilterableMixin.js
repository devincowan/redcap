export default () => ({
    filter(text) {
        const reg_exp = new RegExp(text, 'i')
        console.log('filtering')
        for(let child of this.children) {
            if(child instanceof Node) {
                child.filter(text)
                continue
            }
            if(text.trim()=='') {
                child.hidden = false
                continue
            }
            const searchable_keys = [
                'field',
                'label',
                'description',
                // 'category',
                // 'subcategory',
            ]
            const found = searchable_keys.some(key => {
                return child[key].match(reg_exp)
            })
            child.hidden = !found
        }
        this.notifyObservers(text)
    }
})