package com.fyndable.admin.ui.usage

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import com.fyndable.admin.data.remote.UsageOverviewTenant
import com.fyndable.admin.data.repo.ApiResult
import com.fyndable.admin.data.repo.UsageRepository
import com.fyndable.admin.ui.components.EmptyState
import com.fyndable.admin.ui.components.ErrorView
import com.fyndable.admin.ui.components.LoadingIndicator
import com.fyndable.admin.ui.components.OpsHeader
import com.fyndable.admin.ui.components.SectionHeader
import com.fyndable.admin.ui.components.StatCard
import com.fyndable.admin.ui.components.StatusBadge
import dagger.hilt.android.lifecycle.HiltViewModel
import javax.inject.Inject
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

sealed class UsageState {
    data object Loading : UsageState()
    data class Success(val tenants: List<UsageOverviewTenant>) : UsageState()
    data class Error(val message: String) : UsageState()
}

@HiltViewModel
class UsageViewModel @Inject constructor(
    private val repository: UsageRepository,
) : ViewModel() {

    private val _state = MutableStateFlow<UsageState>(UsageState.Loading)
    val state = _state.asStateFlow()

    fun load() {
        _state.value = UsageState.Loading
        viewModelScope.launch {
            _state.value = try {
                when (val result = repository.overview()) {
                    is ApiResult.Success -> UsageState.Success(result.data.tenants)
                    is ApiResult.Error -> UsageState.Error(result.message)
                }
            } catch (e: Exception) {
                UsageState.Error(e.message ?: e.toString())
            }
        }
    }
}

@Composable
fun UsageScreen(viewModel: UsageViewModel = hiltViewModel()) {
    val state by viewModel.state.collectAsStateWithLifecycle()

    LaunchedEffect(Unit) { if (state is UsageState.Loading) viewModel.load() }

    Scaffold(
        topBar = { OpsHeader(title = "Usage Reports") },
        containerColor = Color(0xFFF8FAFC)
    ) { padding ->
        when (val s = state) {
            is UsageState.Loading -> LoadingIndicator(Modifier.padding(padding))
            is UsageState.Error -> ErrorView(s.message, { viewModel.load() }, Modifier.padding(padding))
            is UsageState.Success -> UsageContent(s.tenants, Modifier.padding(padding))
        }
    }
}

@Composable
private fun UsageContent(tenants: List<UsageOverviewTenant>, modifier: Modifier) {
    val totalCalls = tenants.sumOf { it.usage.apiCalls }
    val activeTenants = tenants.size
    val totalCost = tenants.sumOf { it.usage.apiCost }

    LazyColumn(
        modifier = modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        item {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                StatCard(
                    title = "API Calls",
                    value = String.format("%,d", totalCalls),
                    modifier = Modifier.weight(1f)
                )
                StatCard(
                    title = "Tenants",
                    value = activeTenants.toString(),
                    modifier = Modifier.weight(1f)
                )
            }
            Spacer(Modifier.height(12.dp))
            StatCard(
                title = "Total API Cost (Month)",
                value = "€${"%,.2f".format(totalCost)}",
                modifier = Modifier.fillMaxWidth()
            )
        }

        item {
            SectionHeader("Usage by Tenant")
        }

        if (tenants.isEmpty()) {
            item {
                EmptyState("No usage data available")
            }
        } else {
            items(tenants) { tenant ->
                UsageCard(tenant)
            }
        }
        
        item { Spacer(Modifier.height(16.dp)) }
    }
}

@Composable
private fun UsageCard(tenant: UsageOverviewTenant) {
    val usage = tenant.usage
    val apiCallsLimit = tenant.limits?.apiCalls?.limit?.toInt() ?: 0
    val apiCallsUsed = tenant.limits?.apiCalls?.used?.toInt() ?: usage.apiCalls
    val progress = if (apiCallsLimit > 0) (apiCallsUsed.toFloat() / apiCallsLimit).coerceIn(0f, 1f) else 0f
    val exceeded = tenant.limits?.apiCalls?.exceeded == true

    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
        border = BorderStroke(1.dp, Color(0xFFF1F5F9))
    ) {
        Column(Modifier.padding(20.dp)) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Column(Modifier.weight(1f)) {
                    Text(
                        text = tenant.name,
                        fontWeight = FontWeight.Bold,
                        fontSize = 16.sp,
                        color = Color(0xFF1E293B)
                    )
                    Text(
                        text = tenant.domain.ifBlank { "no-domain.com" },
                        style = MaterialTheme.typography.bodySmall,
                        color = Color(0xFF64748B)
                    )
                }
                StatusBadge(tenant.tier)
            }
            
            Spacer(Modifier.height(20.dp))
            
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text(
                    text = "API Usage",
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold,
                    color = Color(0xFF475569)
                )
                Text(
                    text = "${String.format("%,d", apiCallsUsed)} / ${String.format("%,d", apiCallsLimit)}",
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Black,
                    color = if (exceeded) Color(0xFFEF4444) else Color(0xFF8F39AC)
                )
            }
            
            Spacer(Modifier.height(8.dp))
            
            LinearProgressIndicator(
                progress = { progress },
                modifier = Modifier
                    .fillMaxWidth()
                    .height(8.dp),
                color = if (exceeded) Color(0xFFEF4444) else Color(0xFF8F39AC),
                trackColor = Color(0xFFEEF2FF),
                strokeCap = StrokeCap.Round
            )
            
            Spacer(Modifier.height(16.dp))
            
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(24.dp)
            ) {
                Column {
                    Text("AI COST", fontSize = 10.sp, fontWeight = FontWeight.Black, color = Color(0xFF94A3B8))
                    Text("€${"%,.2f".format(usage.apiCost)}", fontWeight = FontWeight.Bold, color = Color(0xFF1E293B))
                }
                Column {
                    Text("PAGES", fontSize = 10.sp, fontWeight = FontWeight.Black, color = Color(0xFF94A3B8))
                    Text(usage.contentGenerated.toString(), fontWeight = FontWeight.Bold, color = Color(0xFF1E293B))
                }
            }
        }
    }
}
