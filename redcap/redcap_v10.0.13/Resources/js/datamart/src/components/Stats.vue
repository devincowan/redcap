<template>
  <div v-if="total>0" class="stats text-left">
    <table class="table table-bordered table-hover table-striped table-sm" v-if="stats">
      <thead class="thead-light">
        <tr>
          <th scope="col">Data category</th>
          <th scope="col" class="text-right">Total</th>
        </tr>
      </thead>
        <transition-group name="list" tag="tbody" >
          <!-- change animated_stats to stats in the loop to disable animation -->
          <tr scope="col" v-for="(total, category) in animated_stats" :key="`row-${category}`" :class="`resource-${category}`">
            <th scope="row">{{category}}</th>
            <td class="text-right count">{{total}}</td>
          </tr>
        </transition-group>
    </table>
  </div>
  <div v-else />
</template>

<script>
import anime from 'animejs/lib/anime.es.js';
export default {
  name: 'Stats',
  data: () => ({
    animated_stats: {}
  }),
  props: {
    stats: {
      type: Object,
      default: () => ({})
    }
  },
  watch: {
    /**
     * watch the total to trigger the animation
     * NOTE: cannot watch the stats because it is an object
     * and would need a deep watcher to trigger the update
     * 
     * @see https://stackoverflow.com/a/49586158
     */
    total(value, prev) {
      
      for(let resource_type in this.stats) {
        if(isNaN(this.animated_stats[resource_type])) {
            this.$set(this.animated_stats, resource_type, 0)
        }

        const animated_stats = Object.assign({}, this.animated_stats)
        const anime_config = {
            targets: animated_stats,
            round: 1,
            easing: 'linear',
            update: () => {
                this.$set(this.animated_stats, resource_type, animated_stats[resource_type])
            },
            begin: function(anim) {
              const animated_counter = document.querySelector(`tr.resource-${resource_type} .count`)
              if(animated_counter) animated_counter.classList.add('animated')
            },
            complete: function(anim) {
              const animated_counter = document.querySelector(`tr.resource-${resource_type} .count`)
              if(animated_counter) animated_counter.classList.remove('animated')
            }
        }
        const current_count = animated_stats[resource_type]
        const new_value = this.stats[resource_type]
        if(current_count == new_value) continue // ignore if nothing changed
        anime_config[resource_type] = new_value
        anime(anime_config)
      }
    }
  },
  computed: {
    total() {
      let total = 0
      for(let [resource, count] of Object.entries(this.stats)) {
        total += count
      }
      return total
    }
  },
  methods: {}
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
.stats {
  font-size: 13px;
}
thead th {
  font-weight: bold;
}
.count {
  font-weight: 300;
  transform-origin: right;
  transition: font-weight 300ms;
}
.count.animated {
  font-weight: 700;
}
/* transition */
.list-enter-active, .list-leave-active {
  transition: all 1s;
}
.list-enter, .list-leave-to /* .list-leave-active below version 2.1.8 */ {
  opacity: 0;
  /* transform: translateX(30px); */
}
/* transition */
</style>
