<template>
  <AppLayout title="Nova Permissão">
    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Nova Permissão
        </h2>
        <Link
          :href="permissions.index.url()"
          class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
        >
          Voltar
        </Link>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
          <form @submit.prevent="submit" class="p-6">
            <div class="grid grid-cols-1 gap-6">
              <!-- Name -->
              <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                  Nome da Permissão *
                </label>
                <input
                  id="name"
                  v-model="form.name"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  :class="{ 'border-red-500': errors.name }"
                  placeholder="ex: orders.edit"
                />
                <div v-if="errors.name" class="text-red-500 text-sm mt-1">
                  {{ errors.name }}
                </div>
                <p class="text-xs text-gray-500 mt-1">
                  Nome único da permissão (usado no código)
                </p>
              </div>

              <!-- Display Name -->
              <div>
                <label for="display_name" class="block text-sm font-medium text-gray-700 mb-1">
                  Nome de Exibição *
                </label>
                <input
                  id="display_name"
                  v-model="form.display_name"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  :class="{ 'border-red-500': errors.display_name }"
                  placeholder="ex: Editar Pedidos"
                />
                <div v-if="errors.display_name" class="text-red-500 text-sm mt-1">
                  {{ errors.display_name }}
                </div>
              </div>

              <!-- Description -->
              <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">
                  Descrição
                </label>
                <textarea
                  id="description"
                  v-model="form.description"
                  rows="3"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  :class="{ 'border-red-500': errors.description }"
                  placeholder="Descrição detalhada da permissão..."
                ></textarea>
                <div v-if="errors.description" class="text-red-500 text-sm mt-1">
                  {{ errors.description }}
                </div>
              </div>

              <!-- Category -->
              <div>
                <label for="category" class="block text-sm font-medium text-gray-700 mb-1">
                  Categoria *
                </label>
                <select
                  id="category"
                  v-model="form.category"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  :class="{ 'border-red-500': errors.category }"
                >
                  <option value="">Selecione uma categoria</option>
                  <option
                    v-for="(label, key) in categories"
                    :key="key"
                    :value="key"
                  >
                    {{ label }}
                  </option>
                </select>
                <div v-if="errors.category" class="text-red-500 text-sm mt-1">
                  {{ errors.category }}
                </div>
              </div>

              <!-- Roles -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Roles que terão esta permissão
                </label>
                <div class="space-y-2">
                  <label
                    v-for="(label, key) in roles"
                    :key="key"
                    class="flex items-center"
                  >
                    <input
                      v-model="form.roles"
                      :value="key"
                      type="checkbox"
                      class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                    />
                    <span class="ml-2 text-sm text-gray-700">{{ label }}</span>
                  </label>
                </div>
                <div v-if="errors.roles" class="text-red-500 text-sm mt-1">
                  {{ errors.roles }}
                </div>
              </div>

              <!-- Status -->
              <div>
                <label class="flex items-center">
                  <input
                    v-model="form.is_active"
                    type="checkbox"
                    class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                  />
                  <span class="ml-2 text-sm text-gray-700">Permissão ativa</span>
                </label>
                <div v-if="errors.is_active" class="text-red-500 text-sm mt-1">
                  {{ errors.is_active }}
                </div>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-end space-x-4 mt-8">
              <Link
                :href="permissions.index.url()"
                class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
              >
                Cancelar
              </Link>
              <button
                type="submit"
                :disabled="processing"
                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded disabled:opacity-50"
              >
                {{ processing ? 'Salvando...' : 'Salvar' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import permissions from '@/routes/permissions'

const props = defineProps({
  categories: Object,
  roles: Object,
})

const form = useForm({
  name: '',
  display_name: '',
  description: '',
  category: '',
  roles: [],
  is_active: true,
})

const submit = () => {
  form.post(permissions.store.url())
}
</script>
