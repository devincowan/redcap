
class Coding {
    constructor({system='', code='', display=''}) {
        this.system = system
        this.code = code
        this.display = display
    }

    /* isSystem(system) {
        if(typeof system == 'RegExp') {
            const regExp = system
        }else {
            const regExp = new RegExp(system, 'i')
        }
        return this.system.match(regExp)
    } */

}

export default Coding