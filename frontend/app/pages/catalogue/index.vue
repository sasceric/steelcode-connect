<script setup lang="ts">
const localePath = useLocalePath()
await navigateTo(localePath('/catalogue/products'), { replace: true })
</script>
