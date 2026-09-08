<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
defineProps({
    pelanggans: {
        type: Array,
        default: () => [],
    },
});
const calculate = () => {
    router.post('/restitution/calculate', {
        pelanggan_id: pelangganId.value,
        period_start: periodStart.value,
        period_end: periodEnd.value,
        site_ids: selectedSites.value,
    });
};
const pelangganId = ref('');
const sites = ref([]);
const selectedSites = ref([]);
const periodStart = ref('');
const periodEnd = ref('');
const dateError = ref('');
watch([periodStart, periodEnd], ([start, end]) => {
    dateError.value = '';

    if (!start || !end) {
        return;
    }

    if (end < start) {
        dateError.value =
            'Tanggal akhir tidak boleh lebih kecil dari tanggal mulai.';
    }
});
watch(pelangganId, async (id) => {
    sites.value = [];
    selectedSites.value = [];

    if (!id) {
        return;
    }

    const response = await fetch(`/restitution/sites/${id}`);

    sites.value = await response.json();
});
</script>
<template>
    <div>
        <h1>Hitung Restitusi</h1>

        <div>
            <label for="pelanggan"> Pelanggan </label>

            <select id="pelanggan" v-model="pelangganId">
                <option value="">-- Pilih Pelanggan --</option>

                <option
                    v-for="pelanggan in pelanggans"
                    :key="pelanggan.id"
                    :value="pelanggan.id"
                >
                    {{ pelanggan.nama_pelanggan }}
                </option>
            </select>
            <div>
                <label for="period_start"> Tanggal Mulai </label>

                <input id="period_start" type="date" v-model="periodStart" />
            </div>

            <div>
                <label for="period_end"> Tanggal Akhir </label>

                <input id="period_end" type="date" v-model="periodEnd" />
            </div>

            <p v-if="dateError">
                {{ dateError }}
            </p>
            <div v-if="sites.length">
                <h2>Site</h2>

                <div v-for="site in sites" :key="site.id">
                    <label>
                        <input
                            type="checkbox"
                            v-model="selectedSites"
                            :value="site.id"
                        />

                        {{ site.nama_site }}
                    </label>
                </div>

                <button type="button" @click="calculate">
                    Hitung Restitusi
                </button>
            </div>
        </div>
    </div>
</template>
