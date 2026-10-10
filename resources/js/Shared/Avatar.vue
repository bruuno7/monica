<script setup>
defineProps({
  data: Object,
  imgClasses: String,
});
</script>

<template>
  <div v-if="data.type === 'svg'" class="avatar" v-html="data.content" />
  <img v-else :class="imgClasses" class="avatar" :src="data.content" alt="avatar" />
</template>
