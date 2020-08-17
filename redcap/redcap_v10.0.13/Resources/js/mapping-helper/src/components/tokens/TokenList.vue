<template>
  <div class="token-list">
    <button @click="getTokens">Get Tokens</button>
    <table class="table table-bordered thead-light table-striped table" v-if="tokens && tokens.length">
      <thead>
        <th>patient</th>
        <th>mrn</th>
        <th>access_token</th>
        <th>refresh_token</th>
        <th>token_owner</th>
        <th>expiration</th>
      </thead>
      <tbody>
        <tr v-for="(token, index) in tokens" :key="index">
          <td v-text="token.patient" >{{index}}</td>
          <td v-text="token.mrn" >{{index}}</td>
          <td v-text="token.access_token" >{{index}}</td>
          <td v-text="token.refresh_token" >{{index}}</td>
          <td v-text="token.token_owner" >{{index}}</td>
          <td v-text="token.expiration" >{{index}}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
import qs from 'qs'

export default {
  name: 'TokenList',
  props: {
    data: {
      type: Object,
    }
  },
  created() {
    this.getTokens()
  },
  computed: {
    tokens() {
      return this.$store.state.token.list
    }
  },
  methods: {
    getTokens() {
      const query_params = qs.parse(location.search, { ignoreQueryPrefix: true })
      const user = query_params.userid
      this.$store.dispatch('token/fetchList', {user})
    }
  }
}
</script>

<style scoped>
  .token-list table {
    table-layout: fixed;
    width: 100%;
  }
  .token-list table td {
    white-space: pre-wrap;
    /* text-overflow: ellipsis;  */
    word-wrap: break-word;
    vertical-align: top;
  }
</style>
