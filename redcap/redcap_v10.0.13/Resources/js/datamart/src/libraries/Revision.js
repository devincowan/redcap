/**
 * metadata of a revision
 */
class Metadata {
    constructor({ id, request_id, request_status, approved, date, executed, executed_at, creator, project_mrns, fetchable_mrns }) {
        this.creator = new User(creator)
        // make sure that project_mrns and fetchable_mrns are array. json_encode in PHP turns single items array in objects
        if(project_mrns instanceof Object) project_mrns = Object.values(project_mrns)
        if(fetchable_mrns instanceof Object) fetchable_mrns = Object.values(fetchable_mrns)
        Object.assign(this, {id, request_id, request_status, approved, date, executed, executed_at, project_mrns, fetchable_mrns})
    }
}
/**
 * user related to a revision
 */
class User {
    constructor({id, user_email, user_firstname, user_lastname, username}) {
        Object.assign(this, {id, user_email, user_firstname, user_lastname, username})
    }
}


class Revision {

    index = null
    user_id = null
    request_id = null
    dateMin = ''
    dateMax = ''
    mrns = []
    fields = []

    constructor({data, metadata}) {
        this.metadata = new Metadata(metadata)
        this.data = data
        const { dateMin, dateMax, mrns, fields } = data
        Object.assign(this, { dateMin, dateMax, mrns, fields })
    }

    clone() {
        return Object.assign( Object.create( Object.getPrototypeOf(this)), this) 
    }

    setIndex(index) {
        this.index = index
    }

    getCreator() {
        return this.metadata.creator
    }
    getRequestID() {
        return this.metadata.request_id
    }

    getID() {
        return this.metadata.id
    }

    isExpired() {
        const now = new Date().setHours(0,0,0,0) //set hours to 0 for correct comparison
        const {dateMin, dateMax} = this

        let min = isNaN(Date.parse(dateMin)) ? now : new Date(dateMin)
        let max = isNaN(Date.parse(dateMax)) ? now : new Date(dateMax)

        return (min>now) || (max < now)
    }

    isApproved()  {
        return Boolean(this.metadata.approved)
    }

    hasBeenExecuted() {
        return Boolean(this.metadata.executed)
    }

    getTotaltMrns() {
        return this.mrns.length
    }
    
    getTotalProjectMrns() {
        try {
            const { project_mrns=[] } = this.metadata
            return project_mrns.length 
        } catch (error) {
            return 0
        }
    }

    getTotalFetchableMrns() {
        try {
            const { fetchable_mrns=[] } = this.metadata
            return fetchable_mrns.length 
        } catch (error) {
            return 0
        }
    }

    /**
     * check if the revision can be approved
     */
    canBeApprovedByUser(user) {
        if(!user) return false
        return !this.isApproved()  && user.isAdmin()
    }
    
    /**
     * check if the current user can run a revision
     * this ignores the access token status
     */
    canBeRunByUser(user) {
        const canBeRun = this.isApproved()
            && ( user.canRepeatRevision() || (!this.hasBeenExecuted()) )
            && user.hasValidToken()
        return canBeRun
    }

    canBeUsedByUserForNewRevision(user) {
        if(!this.isApproved()) return false
        if(!user) return false
        if(user.isAdmin()) {
            return true
        }else {
            return user.canCreateRevision()
        }
    }
}

export default Revision