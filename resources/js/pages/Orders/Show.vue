<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ArrowLeft, ExternalLink, Package, Truck, CheckCircle, XCircle } from 'lucide-vue-next';

interface OrderStatus {
    picking: boolean;
    packing: boolean;
    picked_up: boolean;
    picking_marked_by?: string;
    packing_marked_by?: string;
    picked_up_marked_by?: string;
    picking_marked_at?: string;
    packing_marked_at?: string;
    picked_up_marked_at?: string;
}

interface StatusHistoryItem {
    id: number;
    status_type: string;
    status_value: boolean;
    user_name: string;
    user_username: string;
    changed_at: string;
    formatted_changed_at: string;
}

interface OrderHistoryItem {
    id: number;
    event_type: string;
    event_description: string;
    formatted_description: string;
    event_icon: string;
    event_color: string;
    user_name: string;
    user_username: string;
    metadata: any;
    created_at: string;
    formatted_created_at: string;
}

interface SyncLog {
    id: number;
    sync_type: string;
    status: string;
    message?: string;
    error_message?: string;
    started_at?: string;
    completed_at?: string;
    duration_ms?: number;
    attempts: number;
}

interface TrackingData {
    id: number;
    invoice_id: number;
    volume_id: number;
    pedido_venda_id: number;
    nota_fiscal_id: number;
    servico_id: number;
    embalagem_id: number;
    codigo_rastreamento: string;
    descricao: string;
    situacao: number;
    origem: string;
    destino: string;
    ultima_alteracao: string;
    url_rastreamento: string;
    peso: number;
    altura: number;
    largura: number;
    comprimento: number;
    diametro: number;
    data_saida: string;
    prazo_entrega_previsto: number;
    frete_previsto: number;
    valor_declarado: number;
    aviso_recebimento: boolean;
    mao_propria: boolean;
    last_sync_at: string;
    next_sync_at: string;
    sync_attempts: number;
    last_error: string;
    raw_data: any;
    created_at: string;
    updated_at: string;
}

interface Order {
    id: number;
    bling_id: string;
    numero: string;
    numero_loja: string;
    data: string;
    data_saida: string;
    data_prevista: string;
    total_produtos: number;
    total: number;
    contato_id: number;
    contato_nome: string;
    contato_tipo_pessoa: string;
    contato_documento: string;
    situacao_id: number;
    situacao_valor: number;
    loja_id: number;
    source: string;
    created_at: string;
    updated_at: string;
    order_status?: OrderStatus;
    status_history?: StatusHistoryItem[];
    sync_logs?: SyncLog[];
    order_history?: OrderHistoryItem[];
    raw_data?: any;
    nota_fiscal_id?: number;
    invoice_numero?: string;
    invoice_chave_acesso?: string;
    tracking_data?: TrackingData[];
    
    // New Bling fields
    tracking_code?: string;
    logistics_provider?: string;
    service_type?: string;
    volume?: number;
    weight?: number;
    tracking_url?: string;
    raw_order_data?: any;
    raw_invoice_data?: any;
    raw_tracking_data?: any;
    
    invoice?: {
        id: number;
        numero: string;
        chave_acesso: string;
        valor_nota: number;
        data_emissao: string;
        link_danfe?: string;
        link_pdf?: string;
        loja_id?: number;
    };
    invoice_raw_data?: any;
    order_data?: {
        id: number;
        numero_loja: string;
        observacoes?: string;
        observacoes_internas?: string;
        nota_fiscal_id?: number;
        raw_data?: any;
        etiqueta_nome?: string;
        etiqueta_endereco?: string;
        etiqueta_numero?: string;
        etiqueta_complemento?: string;
        etiqueta_bairro?: string;
        etiqueta_municipio?: string;
        etiqueta_uf?: string;
        etiqueta_cep?: string;
        transportador_nome?: string;
    };
}

interface User {
    id: number;
    name: string;
    email: string;
    username: string;
    role: string;
}

interface Props {
    order: Order;
    systems_with_colors?: {
        [key: string]: {
            name: string;
            color: string;
        };
    };
}

const props = defineProps<Props>();
const page = usePage();
const user = page.props.auth.user as User;

// Estado para controlar a expansão das seções JSON
const showRawData = ref(true);
const showSyncLogs = ref(true);
const showOrderHistory = ref(true);

const formatDate = (dateString: string) => {
    if (!dateString) return '-';
    
    try {
        // Handle ISO 8601 format - extract date part only to avoid timezone conversion
        let date: Date;
        
        if (dateString.includes('T')) {
            // For ISO format like "2025-10-11T00:00:00.000000Z", extract just the date part
            const datePart = dateString.split('T')[0];
            const [year, month, day] = datePart.split('-');
            date = new Date(parseInt(year), parseInt(month) - 1, parseInt(day));
        } else {
            // Fallback for other formats
            date = new Date(dateString);
        }
        
        // Check if date is valid
        if (isNaN(date.getTime())) {
            return '-';
        }
        
        return date.toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch (error) {
        console.warn('Error formatting date:', dateString, error);
        return '-';
    }
};

const getSystemBadgeClass = (order: Order) => {
    // Try to find the system by loja_id from invoice first
    if (order.invoice?.loja_id) {
        const lojaId = order.invoice.loja_id.toString();
        const systemConfig = props.systems_with_colors?.[lojaId];
        if (systemConfig) {
            return systemConfig.color;
        }
    }
    
    // Fallback to old logic for backward compatibility
    switch (order.source) {
        case 'collmepro':
            return 'bg-blue-600 text-white border border-blue-700';
        case 'collmepro_atacadista':
            return 'bg-green-600 text-white border border-green-700';
        case 'collme_beauty':
            return 'bg-yellow-400 text-gray-900 border border-yellow-500';
        default:
            return 'bg-gray-100 text-gray-800 border border-gray-200';
    }
};

const getStatusTypeDisplayName = (statusType: string) => {
    switch (statusType) {
        case 'picking':
            return 'Separação';   
        case 'packing':
            return 'Embalagem';   
        case 'picked_up':
            return 'Coletado';    
        default:
            return statusType;
    }
};

const getStatusValueDisplay = (statusValue: boolean) => {
    return statusValue ? 'Marcado' : 'Desmarcado';
};

const getStatusValueClass = (statusValue: boolean) => {
    return statusValue 
        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
        : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
};

// Função para formatar dados de tracking no formato da API
const formatTrackingDataForAPI = (tracking: TrackingData) => {
    return {
        data: {
            pedidoVenda: {
                id: tracking.pedido_venda_id || null
            },
            notaFiscal: {
                id: tracking.nota_fiscal_id || null
            },
            servico: {
                id: tracking.servico_id || null
            },
            rastreamento: {
                codigo: tracking.codigo_rastreamento || "",
                descricao: tracking.descricao || "",
                situacao: tracking.situacao || 0,
                origem: tracking.origem || "",
                destino: tracking.destino || "",
                ultimaAlteracao: tracking.ultima_alteracao || "",
                url: tracking.url_rastreamento || ""
            },
            dimensao: {
                peso: parseFloat(tracking.peso?.toString() || "0"),
                altura: parseFloat(tracking.altura?.toString() || "0"),
                largura: parseFloat(tracking.largura?.toString() || "0"),
                comprimento: parseFloat(tracking.comprimento?.toString() || "0"),
                diametro: parseFloat(tracking.diametro?.toString() || "0")
            },
            embalagem: {
                id: tracking.embalagem_id || null
            },
            dataSaida: tracking.data_saida || "",
            prazoEntregaPrevisto: tracking.prazo_entrega_previsto || null,
            fretePrevisto: parseFloat(tracking.frete_previsto?.toString() || "0"),
            valorDeclarado: parseFloat(tracking.valor_declarado?.toString() || "0"),
            avisoRecebimento: tracking.aviso_recebimento || false,
            maoPropria: tracking.mao_propria || false
        }
    };
};
</script>

<template>
    <Head :title="`Pedido ${props.order.numero}`" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link
                        href="/dashboard/orders"
                        class="inline-flex items-center gap-2 rounded-lg border border-sidebar-border/70 px-3 py-2 text-sm font-medium text-foreground hover:bg-sidebar-border/70 dark:border-sidebar-border"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Voltar aos Pedidos
                    </Link>
                    <h1 class="text-2xl font-bold">Pedido {{ props.order.numero }}</h1>
                    <div v-if="props.order.situacao_id === 12" class="flex items-center">
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300">
                            CANCELADO
                        </span>
                    </div>
                </div>
            </div>

            <!-- Order Details -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <!-- Main Info -->
                <div class="xl:col-span-2 space-y-6">
                    <!-- Order Summary Info -->
                    <div class="rounded-lg border border-sidebar-border/70 p-4 sm:p-6 dark:border-sidebar-border">
                        <h2 class="text-lg font-semibold mb-4">Resumo do Pedido</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-muted-foreground">Sistema</label>
                                <div class="mt-1">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="getSystemBadgeClass(props.order)"
                                    >
                                        {{ props.order.source.toUpperCase() }}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-muted-foreground">Número do Pedido</label>
                                <div class="mt-1 text-sm font-medium">{{ props.order.numero }}</div>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-muted-foreground">Cliente</label>
                                <div class="mt-1 text-sm">{{ props.order.contato_nome }}</div>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-muted-foreground">Data do Pedido</label>
                                <div class="mt-1 text-sm">{{ formatDate(props.order.data) }}</div>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-muted-foreground">Total</label>
                                <div class="mt-1 text-sm font-medium">R$ {{ props.order.total }}</div>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-muted-foreground">Total de Produtos</label>
                                <div class="mt-1 text-sm">{{ props.order.total_produtos }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Shipping data -->
                    <div v-if="props.order.order_data" class="rounded-lg border border-sidebar-border/70 p-4 sm:p-6 dark:border-sidebar-border">
                        <h2 class="text-lg font-semibold mb-4">Dados de entrega</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div v-if="props.order.order_data.etiqueta_nome">
                                <label class="text-sm font-medium text-muted-foreground">Nome do Destinatário</label>
                                <div class="mt-1 text-sm">{{ props.order.order_data.etiqueta_nome }}</div>
                            </div>
                            <div v-if="props.order.order_data.etiqueta_endereco">
                                <label class="text-sm font-medium text-muted-foreground">Endereço</label>
                                <div class="mt-1 text-sm">{{ props.order.order_data.etiqueta_endereco }}</div>
                            </div>
                            <div v-if="props.order.order_data.etiqueta_numero">
                                <label class="text-sm font-medium text-muted-foreground">Número</label>
                                <div class="mt-1 text-sm">{{ props.order.order_data.etiqueta_numero }}</div>
                            </div>
                            <div v-if="props.order.order_data.etiqueta_complemento">
                                <label class="text-sm font-medium text-muted-foreground">Complemento</label>
                                <div class="mt-1 text-sm">{{ props.order.order_data.etiqueta_complemento }}</div>
                            </div>
                            <div v-if="props.order.order_data.etiqueta_bairro">
                                <label class="text-sm font-medium text-muted-foreground">Bairro</label>
                                <div class="mt-1 text-sm">{{ props.order.order_data.etiqueta_bairro }}</div>
                            </div>
                            <div v-if="props.order.order_data.etiqueta_municipio">
                                <label class="text-sm font-medium text-muted-foreground">Cidade</label>
                                <div class="mt-1 text-sm">{{ props.order.order_data.etiqueta_municipio }}</div>
                            </div>
                            <div v-if="props.order.order_data.etiqueta_uf">
                                <label class="text-sm font-medium text-muted-foreground">Estado</label>
                                <div class="mt-1 text-sm">{{ props.order.order_data.etiqueta_uf }}</div>
                            </div>
                            <div v-if="props.order.order_data.etiqueta_cep">
                                <label class="text-sm font-medium text-muted-foreground">CEP</label>
                                <div class="mt-1 text-sm">{{ props.order.order_data.etiqueta_cep }}</div>
                            </div>
                            <div v-if="props.order.order_data.transportador_nome" class="md:col-span-2">
                                <label class="text-sm font-medium text-muted-foreground">Transportadora</label>
                                <div class="mt-1 text-sm">{{ props.order.order_data.transportador_nome }}</div>
                            </div>
                        </div>
                    </div>


                    <!-- Tracking Information -->
                    <div v-if="props.order.tracking_code || props.order.logistics_provider" class="rounded-lg border border-sidebar-border/70 p-4 sm:p-6 dark:border-sidebar-border">
                        <h2 class="text-lg font-semibold mb-4">Informações de Rastreamento</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div v-if="props.order.tracking_code">
                                <label class="text-sm font-medium text-muted-foreground">Código de Rastreamento</label>
                                <div class="mt-1 flex items-center gap-2">
                                    <span class="text-sm font-medium">{{ props.order.tracking_code }}</span>
                                    <a
                                        v-if="props.order.tracking_url"
                                        :href="props.order.tracking_url"
                                        target="_blank"
                                        class="inline-flex items-center gap-1 rounded-md bg-blue-600 px-2 py-1 text-xs font-medium text-white hover:bg-blue-700"
                                    >
                                        <ExternalLink class="h-3 w-3" />
                                        Rastrear
                                    </a>
                                </div>
                            </div>
                            <div v-if="props.order.logistics_provider">
                                <label class="text-sm font-medium text-muted-foreground">Transportadora</label>
                                <div class="mt-1 text-sm">{{ props.order.logistics_provider }}</div>
                            </div>
                            <div v-if="props.order.service_type">
                                <label class="text-sm font-medium text-muted-foreground">Tipo de Serviço</label>
                                <div class="mt-1 text-sm">{{ props.order.service_type }}</div>
                            </div>
                            <div v-if="props.order.weight">
                                <label class="text-sm font-medium text-muted-foreground">Peso</label>
                                <div class="mt-1 text-sm">{{ props.order.weight }} kg</div>
                            </div>
                            <div v-if="props.order.volume">
                                <label class="text-sm font-medium text-muted-foreground">Volume</label>
                                <div class="mt-1 text-sm">{{ props.order.volume }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Invoice Information -->
                    <div v-if="props.order.invoice_raw_data || props.order.nota_fiscal_id || props.order.invoice_numero || props.order.invoice" class="rounded-lg border border-sidebar-border/70 p-4 sm:p-6 dark:border-sidebar-border">
                        <h2 class="text-lg font-semibold mb-4">Nota Fiscal Eletrônica (NFE)</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div v-if="props.order.invoice_raw_data?.numero || props.order.invoice?.numero">
                                <label class="text-sm font-medium text-muted-foreground">Número da NFE</label>
                                <div class="mt-1 text-sm font-medium">{{ props.order.invoice_raw_data?.numero || props.order.invoice?.numero }}</div>
                            </div>
                            <div v-if="props.order.invoice_raw_data?.chaveAcesso || props.order.invoice?.chave_acesso">
                                <label class="text-sm font-medium text-muted-foreground">Chave de Acesso</label>
                                <div class="mt-1 text-xs font-mono break-all">{{ props.order.invoice_raw_data?.chaveAcesso || props.order.invoice?.chave_acesso }}</div>
                            </div>
                            <div v-if="props.order.invoice_raw_data?.valorNota || props.order.invoice_raw_data?.total || props.order.invoice?.valor_nota">
                                <label class="text-sm font-medium text-muted-foreground">Valor da NFE</label>
                                <div class="mt-1 text-sm font-medium">R$ {{ props.order.invoice_raw_data?.valorNota || props.order.invoice_raw_data?.total || props.order.invoice?.valor_nota }}</div>
                            </div>
                            <div v-if="props.order.invoice_raw_data?.dataEmissao || props.order.invoice?.data_emissao">
                                <label class="text-sm font-medium text-muted-foreground">Data de Emissão</label>
                                <div class="mt-1 text-sm">{{ formatDate(props.order.invoice_raw_data?.dataEmissao || props.order.invoice?.data_emissao) }}</div>
                            </div>
                            <div v-if="props.order.invoice_raw_data?.linkDanfe || props.order.invoice_raw_data?.linkPDF || props.order.invoice?.link_danfe || props.order.invoice?.link_pdf" class="sm:col-span-2">
                                <label class="text-sm font-medium text-muted-foreground">Links</label>
                                <div class="mt-2 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                                    <a
                                        v-if="props.order.invoice_raw_data?.linkPDF || props.order.invoice?.link_pdf"
                                        :href="props.order.invoice_raw_data?.linkPDF || props.order.invoice?.link_pdf"
                                        target="_blank"
                                        class="flex flex-col items-center justify-center gap-1 rounded-lg bg-primary px-3 py-3 text-xs font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
                                    >
                                        <ExternalLink class="h-4 w-4" />
                                        <span class="text-center leading-tight">Baixar<br>PDF</span>
                                    </a>
                                    <a
                                        v-if="props.order.invoice_raw_data?.linkDanfe || props.order.invoice?.link_danfe"
                                        :href="props.order.invoice_raw_data?.linkDanfe || props.order.invoice?.link_danfe"
                                        target="_blank"
                                        class="flex flex-col items-center justify-center gap-1 rounded-lg border border-sidebar-border/70 px-3 py-3 text-xs font-medium text-foreground hover:bg-sidebar-border/70 dark:border-sidebar-border transition-colors"
                                    >
                                        <ExternalLink class="h-4 w-4" />
                                        <span class="text-center leading-tight">Ver<br>DANFE</span>
                                    </a>
                                    <a
                                        :href="`https://www.bling.com.br/vendas.php#edit/${props.order?.bling_id}`"
                                        target="_blank"
                                        class="flex flex-col items-center justify-center gap-1 rounded-lg border border-sidebar-border/70 px-3 py-3 text-xs font-medium text-foreground hover:bg-sidebar-border/70 dark:border-sidebar-border transition-colors"
                                    >
                                        <ExternalLink class="h-4 w-4" />
                                        <span class="text-center leading-tight">Ver pedido<br>no Bling</span>
                                    </a>
                                    <a
                                        :href="`https://www.bling.com.br/notas.fiscais.php#edit/${props.order?.raw_invoice_data?.id}`"
                                        target="_blank"
                                        class="flex flex-col items-center justify-center gap-1 rounded-lg border border-sidebar-border/70 px-3 py-3 text-xs font-medium text-foreground hover:bg-sidebar-border/70 dark:border-sidebar-border transition-colors"
                                    >
                                        <ExternalLink class="h-4 w-4" />
                                        <span class="text-center leading-tight">Ver NFE<br>no Bling</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                      </div>

                      <!-- Order Items -->
                    <div v-if="props.order.raw_order_data?.itens" class="rounded-lg border border-sidebar-border/70 p-4 sm:p-6 dark:border-sidebar-border">
                        <h2 class="text-lg font-semibold mb-4">Itens do Pedido</h2>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-sidebar-border/70 dark:border-sidebar-border">
                                        <th class="text-left py-2 font-medium text-muted-foreground">Produto</th>
                                        <th class="text-left py-2 font-medium text-muted-foreground">Quantidade</th>
                                        <th class="text-left py-2 font-medium text-muted-foreground">Valor Unit.</th>
                                        <th class="text-left py-2 font-medium text-muted-foreground">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr 
                                        v-for="item in props.order.raw_order_data.itens" 
                                        :key="item.id"
                                        class="border-b border-sidebar-border/70 dark:border-sidebar-border last:border-b-0"
                                    >
                                        <td class="py-2">
                                            <div>
                                                <div class="font-medium">{{ item.descricao }}</div>
                                                <div class="text-xs text-muted-foreground">{{ item.codigo || '-' }}</div>
                                            </div>
                                        </td>
                                        <td class="py-2">{{ item.quantidade || 0 }}</td>
                                        <td class="py-2">R$ {{ item.valor || '0,00' }}</td>
                                        <td class="py-2 font-medium">R$ {{ item.quantidade * item.valor || '0,00' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                      <!-- Order Observations -->
                    <div v-if="props.order.raw_order_data?.observacoes || props.order.raw_order_data?.observacoesInternas" class="rounded-lg border border-sidebar-border/70 p-4 sm:p-6 dark:border-sidebar-border">
                        <h2 class="text-lg font-semibold mb-4">Observações</h2>
                        <div class="space-y-4">
                            <div v-if="props.order.raw_order_data?.observacoes">
                                <label class="text-sm font-medium text-muted-foreground">Observações Gerais</label>
                                <div class="mt-1 p-3 bg-sidebar-border/20 rounded-lg text-sm whitespace-pre-wrap">{{ props.order.raw_order_data.observacoes }}</div>
                            </div>
                            <div v-if="props.order.raw_order_data?.observacoesInternas">
                                <label class="text-sm font-medium text-muted-foreground">Observações Internas</label>
                                <div class="mt-1 p-3 bg-orange-50 dark:bg-orange-900/20 rounded-lg text-sm whitespace-pre-wrap">{{ props.order.raw_order_data.observacoesInternas }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Sidebar -->
                <div class="space-y-6">
                    <!-- Order Status -->
                    <div class="rounded-lg border border-sidebar-border/70 p-4 sm:p-6 dark:border-sidebar-border">
                        <h2 class="text-lg font-semibold mb-4">Status do Pedido</h2>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Package class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Separado</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <CheckCircle v-if="props.order.order_status?.picking" class="h-4 w-4 text-green-600" />
                                    <XCircle v-else class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-xs text-muted-foreground">
                                        {{ props.order.order_status?.picking ? 'Concluído' : 'Pendente' }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Package class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Embalado</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <CheckCircle v-if="props.order.order_status?.packing" class="h-4 w-4 text-green-600" />
                                    <XCircle v-else class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-xs text-muted-foreground">
                                        {{ props.order.order_status?.packing ? 'Concluído' : 'Pendente' }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Truck class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Coletado</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <CheckCircle v-if="props.order.order_status?.picked_up" class="h-4 w-4 text-green-600" />
                                    <XCircle v-else class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-xs text-muted-foreground">
                                        {{ props.order.order_status?.picked_up ? 'Concluído' : 'Pendente' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- Status History -->
                    <div v-if="props.order.status_history && props.order.status_history.length > 0" class="rounded-lg border border-sidebar-border/70 p-4 sm:p-6 dark:border-sidebar-border">
                        <h2 class="text-lg font-semibold mb-4">Histórico de Alterações</h2>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-sidebar-border/70 dark:border-sidebar-border">
                                        <th class="text-left py-2 font-medium text-muted-foreground">Status</th>
                                        <th class="text-left py-2 font-medium text-muted-foreground">Ação</th>
                                        <th class="text-left py-2 font-medium text-muted-foreground">Usuário</th>
                                        <th class="text-left py-2 font-medium text-muted-foreground">Data/Hora</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr 
                                        v-for="history in props.order.status_history" 
                                        :key="history.id"
                                        class="border-b border-sidebar-border/70 dark:border-sidebar-border last:border-b-0"
                                    >
                                        <td class="py-2">
                                            <span class="font-medium">{{ getStatusTypeDisplayName(history.status_type) }}</span>
                                        </td>
                                        <td class="py-2">
                                            <span 
                                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="getStatusValueClass(history.status_value)"
                                            >
                                                {{ getStatusValueDisplay(history.status_value) }}
                                            </span>
                                        </td>
                                        <td class="py-2">
                                            <div>
                                                <div class="font-medium">{{ history.user_name }}</div>
                                                <div class="text-xs text-muted-foreground">@{{ history.user_username }}</div>
                                            </div>
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            {{ history.formatted_changed_at }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>


                    <!-- Sync Logs -->
                    <div v-if="props.order.sync_logs && props.order.sync_logs.length > 0" class="rounded-lg border border-sidebar-border/70 p-4 sm:p-6 dark:border-sidebar-border">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-semibold">Histórico de Sincronização</h2>
                            <button
                                @click="showSyncLogs = !showSyncLogs"
                                class="text-sm text-primary hover:text-primary/80"
                            >
                                {{ showSyncLogs ? 'Ocultar' : 'Mostrar' }}
                            </button>
                        </div>
                        <div v-if="showSyncLogs" class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-sidebar-border/70">
                                        <th class="pb-2 text-left font-medium">Tipo</th>
                                        <th class="pb-2 text-left font-medium">Status</th>
                                        <th class="pb-2 text-left font-medium">Mensagem</th>
                                        <th class="pb-2 text-left font-medium">Data</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="log in props.order.sync_logs"
                                        :key="log.id"
                                        class="border-b border-sidebar-border/70 last:border-0"
                                    >
                                        <td class="py-2">
                                            <span
                                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="{
                                                    'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300': log.sync_type === 'order_import',
                                                    'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300': log.sync_type === 'invoice_sync',
                                                    'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300': log.sync_type === 'tracking_fetch',
                                                }"
                                            >
                                                {{ log.sync_type === 'order_import' ? 'Importação' : log.sync_type === 'invoice_sync' ? 'NFE' : 'Rastreio' }}
                                            </span>
                                        </td>
                                        <td class="py-2">
                                            <span
                                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="{
                                                    'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300': log.status === 'success',
                                                    'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300': log.status === 'failed',
                                                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300': log.status === 'skipped',
                                                    'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300': log.status === 'pending' || log.status === 'processing',
                                                }"
                                            >
                                                {{ log.status === 'success' ? 'Sucesso' : log.status === 'failed' ? 'Falha' : log.status === 'skipped' ? 'Pulado' : log.status }}
                                            </span>
                                        </td>
                                        <td class="py-2 text-muted-foreground max-w-xs truncate">
                                            {{ log.error_message || log.message || '-' }}
                                        </td>
                                        <td class="py-2 text-muted-foreground text-xs">
                                            {{ log.completed_at || log.started_at || '-' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Order History -->
                    <div v-if="props.order.order_history && props.order.order_history.length > 0" class="rounded-lg border border-sidebar-border/70 p-4 sm:p-6 dark:border-sidebar-border">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-semibold">Histórico Completo do Pedido</h2>
                            <button
                                @click="showOrderHistory = !showOrderHistory"
                                class="text-sm text-primary hover:text-primary/80"
                            >
                                {{ showOrderHistory ? 'Ocultar' : 'Mostrar' }}
                            </button>
                        </div>
                        <div v-if="showOrderHistory" class="space-y-3">
                            <div
                                v-for="history in props.order.order_history"
                                :key="history.id"
                                class="flex items-start space-x-3 p-3 rounded-lg border border-sidebar-border/50"
                            >
                                <div class="flex-shrink-0">
                                    <div
                                        class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-medium"
                                        :class="{
                                            'bg-blue-500': history.event_color === 'blue',
                                            'bg-green-500': history.event_color === 'green',
                                            'bg-purple-500': history.event_color === 'purple',
                                            'bg-yellow-500': history.event_color === 'yellow',
                                            'bg-orange-500': history.event_color === 'orange',
                                            'bg-gray-500': history.event_color === 'gray',
                                        }"
                                    >
                                        <span v-if="history.event_icon === 'download'">↓</span>
                                        <span v-else-if="history.event_icon === 'package'">📦</span>
                                        <span v-else-if="history.event_icon === 'box'">📦</span>
                                        <span v-else-if="history.event_icon === 'truck'">🚚</span>
                                        <span v-else-if="history.event_icon === 'file-text'">📄</span>
                                        <span v-else-if="history.event_icon === 'search'">🔍</span>
                                        <span v-else-if="history.event_icon === 'message-square'">💬</span>
                                        <span v-else-if="history.event_icon === 'refresh-cw'">🔄</span>
                                        <span v-else-if="history.event_icon === 'edit'">✏️</span>
                                        <span v-else>📋</span>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-medium text-foreground">
                                            {{ history.formatted_description }}
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ history.formatted_created_at }}
                                        </p>
                                    </div>
                                    <div class="flex items-center space-x-2 mt-1">
                                        <span class="text-xs text-muted-foreground">
                                            por {{ history.user_name }}
                                        </span>
                                        <span v-if="history.metadata && Object.keys(history.metadata).length > 0" class="text-xs text-muted-foreground">
                                            •
                                        </span>
                                        <span v-if="history.metadata?.tracking_code && history.metadata?.logistics_provider" class="text-xs text-muted-foreground">
                                            Código:
                                            <span v-if="history.metadata?.tracking_code" class="text-xs text-blue-600 dark:text-blue-400">
                                                {{ history.metadata.tracking_code }}
                                            </span>
                                        </span>
                                        <span v-if="history.metadata?.logistics_provider" class="text-xs text-green-600 dark:text-green-400">
                                            {{ history.metadata.logistics_provider }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Raw Data (JSON) - Developer Only -->
                    <div v-if="user?.role === 'developer'" class="rounded-lg border border-sidebar-border/70 p-4 sm:p-6 dark:border-sidebar-border">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-semibold">Dados Brutos (JSON)</h2>
                            <button
                                @click="showRawData = !showRawData"
                                class="text-sm text-primary hover:text-primary/80"
                            >
                                {{ showRawData ? 'Ocultar' : 'Mostrar' }}
                            </button>
                        </div>
                        <div v-if="showRawData" class="space-y-4">
                            <div v-if="props.order.raw_order_data">
                                <h3 class="text-sm font-medium text-muted-foreground mb-2">order</h3>
                                <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg text-xs overflow-x-auto max-h-96"><code>{{ JSON.stringify(props.order.raw_order_data, null, 2) }}</code></pre>
                            </div>
                            <div v-if="props.order.raw_invoice_data">
                                <h3 class="text-sm font-medium text-muted-foreground mb-2">invoice</h3>
                                <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg text-xs overflow-x-auto max-h-96"><code>{{ JSON.stringify(props.order.raw_invoice_data, null, 2) }}</code></pre>
                            </div>
                            <div v-if="props.order.raw_tracking_data">
                                <h3 class="text-sm font-medium text-muted-foreground mb-2">tracking</h3>
                                <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg text-xs overflow-x-auto max-h-96"><code>{{ JSON.stringify(props.order.raw_tracking_data, null, 2) }}</code></pre>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-muted-foreground mb-2">order summary</h3>
                                <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg text-xs overflow-x-auto"><code>{{ JSON.stringify({
                                    id: props.order.id,
                                    bling_id: props.order.bling_id,
                                    numero: props.order.numero,
                                    numero_loja: props.order.numero_loja,
                                    data: props.order.data,
                                    data_saida: props.order.data_saida,
                                    data_prevista: props.order.data_prevista,
                                    total_produtos: props.order.total_produtos,
                                    total: props.order.total,
                                    contato_id: props.order.contato_id,
                                    contato_nome: props.order.contato_nome,
                                    contato_tipo_pessoa: props.order.contato_tipo_pessoa,
                                    contato_documento: props.order.contato_documento,
                                    situacao_id: props.order.situacao_id,
                                    situacao_valor: props.order.situacao_valor,
                                    loja_id: props.order.loja_id,
                                    source: props.order.source,
                                    created_at: props.order.created_at,
                                    updated_at: props.order.updated_at
                                }, null, 2) }}</code></pre>
                            </div>
                            <div v-if="props.order.raw_data">
                                <h3 class="text-sm font-medium text-muted-foreground mb-2">Order Data (Raw Data)</h3>
                                <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg text-xs overflow-x-auto"><code>{{ JSON.stringify(props.order.raw_data, null, 2) }}</code></pre>
                            </div>
                            <div v-if="props.order.invoice_raw_data">
                                <h3 class="text-sm font-medium text-muted-foreground mb-2">Invoice (Raw Data)</h3>
                                <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg text-xs overflow-x-auto"><code>{{ JSON.stringify(props.order.invoice_raw_data, null, 2) }}</code></pre>
                            </div>
                            <div v-if="props.order.tracking_data && props.order.tracking_data.length > 0">
                                <h3 class="text-sm font-medium text-muted-foreground mb-2">Tracking Data (API Format)</h3>
                                <div v-for="(tracking, index) in props.order.tracking_data" :key="tracking.id" class="mb-4">
                                    <h4 class="text-xs font-medium text-muted-foreground mb-2">Volume ID: {{ tracking.volume_id }}</h4>
                                    <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg text-xs overflow-x-auto"><code>{{ JSON.stringify(formatTrackingDataForAPI(tracking), null, 2) }}</code></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
