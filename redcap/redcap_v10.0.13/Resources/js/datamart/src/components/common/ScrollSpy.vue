<template>
  <div>
    <button class="btn btn-light btn-sm" @click="onClick">load more...</button>
  </div>
</template>

<script>
export default {
  name: 'ScrollSpy',
  mounted() {
    this.registerIntersectionObserver()
  },
  methods: {
    registerIntersectionObserver() {
      // const observedElement = this.$el.querySelector('div.intersection')
      const observedElement = this.$el
      if(observedElement) {
        const options = {
          root: null, // null default to viewport
          rootMargin: '0px',
          threshold: 0.1
        }
        const onIntersection = (entries, observer) => {
          entries.forEach(entry => {
            this.$emit('intersect')
          })
        }

        var observer = new IntersectionObserver(onIntersection, options)
        observer.observe(observedElement)
      }
    },
    onClick(e) {
      this.$emit('click')
    }
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>

</style>
