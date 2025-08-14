<script setup lang="ts">
const currentOffcanvas = useState<String | null>('offcanvas')
</script>

<template>
  <div class="page-wrap" :data-offcanvas="currentOffcanvas">
    <main id="main" class="main-content">
      <slot />
    </main>

    <Transition name="fade">
      <div
        v-if="currentOffcanvas"
        class="overlay"
        @click.prevent="currentOffcanvas = null"
      />
    </Transition>
  </div>
</template>

<style lang="scss">
.page-wrap {
  display: grid;
  grid-template-rows: auto auto 1fr auto;
  min-height: 100vh;

  &[data-offcanvas] {
    max-height: 100vh;
    overflow: hidden;
  }
}

.overlay {
  background-color: hsla(from var(--color-black) h s l / 0.5);
  inset: 0;
  position: absolute;
  z-index: 899;

  &.fade-enter-active {
    transition-timing-function: var(--ease-out-sine);
  }

  &.fade-leave-active {
    transition-timing-function: var(--ease-in-sine);
  }
}
</style>
