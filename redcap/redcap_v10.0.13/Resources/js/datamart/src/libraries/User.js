class User {
    constructor({can_create_revision, can_repeat_revision, can_use_datamart, has_valid_access_token, id, super_user, user_email, user_firstname, user_lastname, username, can_perform_standalone_launch}) {
        Object.assign(this, {can_create_revision, can_repeat_revision, can_use_datamart, has_valid_access_token, id, super_user, user_email, user_firstname, user_lastname, username, can_perform_standalone_launch})
    }

    hasValidToken() {
        return Boolean(this.has_valid_access_token)
    }

    isAdmin() {
        return Boolean(this.super_user)
    }

    canCreateRevision() {
        return Boolean(this.can_create_revision)
    }

    canRepeatRevision() {
        return Boolean(this.can_repeat_revision)
    }

    canUseDatamart() {
        return Boolean(this.can_use_datamart)
    }

    canPerformStandaloneLaunch() {
        if(!this.can_perform_standalone_launch) return false
        return this.can_perform_standalone_launch
    }

    /**
     * check if the user can approve a revision
     */
    canApproveRevision(revision) {
        return !revision.isApproved()  && this.isAdmin()
    }
    
    /**
     * check if the current user can run a revision
     * this ignores the access token status
     */
    canRunRevision(revision) {
        const { metadata: { approved, executed, creator: user } } = this
        const canBeRun = revision.isApproved() && ( this.canRepeatRevision() || (!revision.hasBeenExecuted() && !this.canRepeatRevision()) ) 
        return canBeRun
    }
}


export default User