<template>
  <div>
    <div class="card" v-if="this.tokens.length==0 && this.settings.standalone_launch_enabled">
        <div class="card-header">No access tokens avaiable</div>
        <div class="card-body">
            <!-- <h5 class="card-title">No access tokens avaiable</h5> -->
            <p class="card-text">{{settings.lang.ehr_launch_modal_02}}</p>
            <p class="card-text font-italic">{{settings.lang.ehr_launch_modal_03}}</p>
            <p class="card-text font-weight-bold">EHR system: {{settings.ehr_system_name}}</p>
            <button class="btn btn-primary" type="button" @click="onClick">Log in</button>
        </div>
    </div>
    <slot v-else/>
  </div>
</template>

<script>
/**
 * protection proxy that checks if the user has available tokens
 */
export default {
    name: 'access-token-checker',
    data: () => ({
        check_interval: null,
        interval_time: 4000,
        loading: false,
    }),
    /* mounted() {
        this.check_interval = setInterval(this.checkUserInfo, this.interval_time)
    },
    beforeDestroy() {
        clearInterval(this.check_interval)
    }, */
    computed: {

        settings() {
            const { settings: {
                project_id=null,
                lang=null,
                standalone_authentication_flow=null,
                standalone_launch_enabled=null,
                standalone_launch_url=null,
                ehr_system_name=null
            } } = this.$store.state.settings
            return {
                project_id,
                lang,
                standalone_authentication_flow,
                standalone_launch_enabled,
                standalone_launch_url,
                ehr_system_name
            }
        },
        tokens() {
            return this.$store.state.user.tokens
        },
    },
    methods: {
        onClick() {
            const {standalone_launch_url} = this.settings
            window.open(standalone_launch_url, '_blank')
        },
        async checkUserInfo() {
            try {
                this.loading = true
                await this.$store.dispatch('user/fetchInfo')
            } catch (error) {
                // console.error(error)
            }finally {
                this.loading = false
            }
        }
    }
}
</script>

<style>

</style>