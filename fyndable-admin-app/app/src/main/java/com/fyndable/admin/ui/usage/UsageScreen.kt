package com.fyndable.admin.ui.usage

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
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
import com.fyndable.admin.ui.components.SectionHeader
import com.fyndable.admin.ui.components.StatCard
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
            when (val result = repository.overview()) {
                is ApiResult.Success -> _state.value = UsageState.Success(result.data.tenants)
                is ApiResult.Error -> _state.value = UsageState.Error(result.message)
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun UsageScreen(viewModel: UsageViewModel = hiltViewModel()) {
    val state by viewModel.state.collectAsStateWithLifecycle()

    LaunchedEffect(Unit) { if (state is UsageState.Loading) viewModel.load() }

    Scaffold(topBar = { TopAppBar(title = { Text("Usage Reports") }) }) { padding ->
        when (val s = state) {
            is UsageState.Loading -> LoadingIndicator(Modifier.padding(padding))
            is UsageState.Error -> ErrorView(s.message, { viewModel.load() }, Modifier.padding(padding))
            is UsageState.Success -> {
                if (s.tenants.isEmpty()) {
                    EmptyState("No active tenants", Modifier.padding(padding))
                } else {
                    LazyColumn(
                        modifier = Modifier.fillMaxSize().padding(padding).padding(16.dp),
                        verticalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        items(s.tenants) { tenant -> UsageCard(tenant) }
                    }
                }
            }
        }
    }
}

@Composable
private fun UsageCard(tenant: UsageOverviewTenant) {
    val usage = tenant.usage
    val apiCallsLimit = tenant.limits?.apiCalls?.limit ?: 0
    val apiCallsUsed = tenant.limits?.apiCalls?.used ?: usage.apiCalls
    val exceeded = tenant.limits?.apiCalls?.exceeded == true

    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(1.dp),
    ) {
        Column(Modifier.padding(16.dp)) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text(tenant.name, fontWeight = FontWeight.Bold)
                Text(tenant.tier.replaceFirstChar { it.uppercase() }, color = MaterialTheme.colorScheme.primary)
            }
            Text(tenant.domain.ifBlank { "No domain" }, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            if (tenant.onboarding.completed) {
                Text("✓ Wizard completed", style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.primary)
            } else {
                Text("• Wizard not completed", style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            Spacer(Modifier.height(8.dp))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Column(Modifier.weight(1f)) {
                    Text("API Calls", style = MaterialTheme.typography.labelMedium)
                    Text(
                        "$apiCallsUsed / $apiCallsLimit",
                        fontWeight = FontWeight.SemiBold,
                        color = if (exceeded) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurface,
                    )
                }
                Column(Modifier.weight(1f)) {
                    Text("Est. Cost", style = MaterialTheme.typography.labelMedium)
                    Text("€${"%,.2f".format(usage.apiCost)}", fontWeight = FontWeight.SemiBold)
                }
                Column(Modifier.weight(1f)) {
                    Text("Content", style = MaterialTheme.typography.labelMedium)
                    Text(usage.contentGenerated.toString(), fontWeight = FontWeight.SemiBold)
                }
            }
        }
    }
}
