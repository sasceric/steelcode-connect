<script setup lang="ts">
const localePath = useLocalePath()
await navigateTo(localePath('/inventory/stock'), { replace: true })
</script>
