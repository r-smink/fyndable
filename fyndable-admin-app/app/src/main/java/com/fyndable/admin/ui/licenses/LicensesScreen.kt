package com.fyndable.admin.ui.licenses

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
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ExposedDropdownMenuBox
import androidx.compose.material3.ExposedDropdownMenuDefaults
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Button
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import com.fyndable.admin.data.remote.GenerateLicenseRequest
import com.fyndable.admin.data.remote.LicenseListResponse
import com.fyndable.admin.data.remote.License
import com.fyndable.admin.data.repo.ApiResult
import com.fyndable.admin.data.repo.LicenseRepository
import com.fyndable.admin.ui.components.EmptyState
import com.fyndable.admin.ui.components.ErrorView
import com.fyndable.admin.ui.components.LoadingIndicator
import com.fyndable.admin.ui.components.StatusBadge
import dagger.hilt.android.lifecycle.HiltViewModel
import javax.inject.Inject
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

sealed class LicensesState {
    data object Loading : LicensesState()
    data class Success(val licenses: List<License>, val total: Int) : LicensesState()
    data class Error(val message: String) : LicensesState()
}

@HiltViewModel
class LicensesViewModel @Inject constructor(
    private val repository: LicenseRepository,
) : ViewModel() {

    private val _state = MutableStateFlow<LicensesState>(LicensesState.Loading)
    val state = _state.asStateFlow()

    var statusFilter by mutableStateOf<String?>(null)
        private set
    var tierFilter by mutableStateOf<String?>(null)
        private set
    var searchQuery by mutableStateOf("")
        private set

    fun setFilters(status: String?, tier: String?, search: String) {
        statusFilter = status?.takeIf { it.isNotBlank() }
        tierFilter = tier?.takeIf { it.isNotBlank() }
        searchQuery = search
        load()
    }

    fun load() {
        _state.value = LicensesState.Loading
        viewModelScope.launch {
            when (val result = repository.list(statusFilter, null, tierFilter, searchQuery.ifBlank { null }, 100, 0)) {
                is ApiResult.Success -> _state.value = LicensesState.Success(result.data.licenses, result.data.total)
                is ApiResult.Error -> _state.value = LicensesState.Error(result.message)
            }
        }
    }

    fun generate(req: GenerateLicenseRequest, onDone: (Boolean, String?) -> Unit) {
        viewModelScope.launch {
            when (val result = repository.generate(req)) {
                is ApiResult.Success -> {
                    load()
                    val keys = result.data.result.licenses?.map { it.licenseKey } ?: listOfNotNull(result.data.result.licenseKey)
                    onDone(true, keys.joinToString("\n"))
                }
                is ApiResult.Error -> onDone(false, result.message)
            }
        }
    }

    fun revoke(key: String, reason: String, onDone: (Boolean, String?) -> Unit) {
        viewModelScope.launch {
            when (val result = repository.revoke(key, reason)) {
                is ApiResult.Success -> { load(); onDone(true, null) }
                is ApiResult.Error -> onDone(false, result.message)
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun LicensesScreen(viewModel: LicensesViewModel = hiltViewModel()) {
    val state by viewModel.state.collectAsStateWithLifecycle()
    var showGenerateDialog by remember { mutableStateOf(false) }
    var revokeTarget by remember { mutableStateOf<License?>(null) }

    Scaffold(
        topBar = {
            TopAppBar(title = { Text("Licenses") })
        },
        floatingActionButton = {
            FloatingActionButton(onClick = { showGenerateDialog = true }) {
                Icon(Icons.Filled.Add, contentDescription = "Generate")
            }
        },
    ) { padding ->
        Column(modifier = Modifier.fillMaxSize().padding(padding).padding(horizontal = 16.dp)) {
            // Filters
            LicenseFilters(
                status = viewModel.statusFilter ?: "",
                tier = viewModel.tierFilter ?: "",
                search = viewModel.searchQuery,
                onApply = { s, t, q -> viewModel.setFilters(s, t, q) },
            )
            Spacer(Modifier.height(8.dp))

            when (val s = state) {
                is LicensesState.Loading -> LoadingIndicator()
                is LicensesState.Error -> ErrorView(s.message, onRetry = { viewModel.load() })
                is LicensesState.Success -> {
                    if (s.licenses.isEmpty()) {
                        EmptyState("No licenses found")
                    } else {
                        LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            items(s.licenses) { license ->
                                LicenseCard(
                                    license = license,
                                    onRevoke = { revokeTarget = license },
                                )
                            }
                        }
                    }
                }
            }
        }
    }

    if (showGenerateDialog) {
        GenerateLicenseDialog(
            onDismiss = { showGenerateDialog = false },
            onGenerate = { req ->
                viewModel.generate(req) { success, keys ->
                    showGenerateDialog = false
                    if (!success) {
                        // Could show a snackbar; for now re-open not needed
                    }
                }
            },
        )
    }

    revokeTarget?.let { license ->
        RevokeDialog(
            license = license,
            onDismiss = { revokeTarget = null },
            onConfirm = { reason ->
                viewModel.revoke(license.licenseKey, reason) { _, _ -> revokeTarget = null }
            },
        )
    }
}

@Composable
private fun LicenseFilters(
    status: String,
    tier: String,
    search: String,
    onApply: (String, String, String) -> Unit,
) {
    var statusVal by remember(status) { mutableStateOf(status) }
    var tierVal by remember(tier) { mutableStateOf(tier) }
    var searchVal by remember(search) { mutableStateOf(search) }

    Column(modifier = Modifier.verticalScroll(rememberScrollState())) {
        OutlinedTextField(
            value = searchVal,
            onValueChange = { searchVal = it },
            label = { Text("Search") },
            leadingIcon = { Icon(Icons.Filled.Search, contentDescription = null) },
            singleLine = true,
            modifier = Modifier.fillMaxWidth(),
        )
        Spacer(Modifier.height(8.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            OutlinedTextField(
                value = statusVal,
                onValueChange = { statusVal = it },
                label = { Text("Status") },
                singleLine = true,
                modifier = Modifier.weight(1f),
            )
            OutlinedTextField(
                value = tierVal,
                onValueChange = { tierVal = it },
                label = { Text("Tier") },
                singleLine = true,
                modifier = Modifier.weight(1f),
            )
        }
        Spacer(Modifier.height(8.dp))
        OutlinedButton(onClick = { onApply(statusVal, tierVal, searchVal) }) {
            Text("Apply Filters")
        }
    }
}

@Composable
private fun LicenseCard(license: License, onRevoke: () -> Unit) {
    val clipboard = LocalClipboardManager.current
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(1.dp),
    ) {
        Column(Modifier.padding(16.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
            ) {
                Text(
                    text = license.licenseKey.take(24) + "…",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                )
                StatusBadge(license.status)
            }
            Spacer(Modifier.height(4.dp))
            Text("${license.tier.replaceFirstChar { it.uppercase() }} · ${license.licenseType}", style = MaterialTheme.typography.bodyMedium)
            license.assignedTo?.takeIf { it.isNotBlank() }?.let {
                Text(it, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            Spacer(Modifier.height(8.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                TextButton(onClick = { clipboard.setText(AnnotatedString(license.licenseKey)) }) { Text("Copy Key") }
                if (license.status != "revoked") {
                    TextButton(onClick = onRevoke) { Text("Revoke") }
                }
            }
        }
    }
}

@Composable
private fun GenerateLicenseDialog(
    onDismiss: () -> Unit,
    onGenerate: (GenerateLicenseRequest) -> Unit,
) {
    var count by remember { mutableStateOf("1") }
    var type by remember { mutableStateOf("paid") }
    var tier by remember { mutableStateOf("starter") }
    var maxSites by remember { mutableStateOf("1") }
    var apiLimit by remember { mutableStateOf("0") }
    var expiresDays by remember { mutableStateOf("") }
    var assignedTo by remember { mutableStateOf("") }
    var notes by remember { mutableStateOf("") }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Generate License Keys") },
        text = {
            Column(modifier = Modifier.verticalScroll(rememberScrollState())) {
                OutlinedTextField(value = count, onValueChange = { count = it }, label = { Text("Count (1-100)") }, singleLine = true, modifier = Modifier.fillMaxWidth())
                Spacer(Modifier.height(8.dp))
                DropdownSelector(label = "Type", options = listOf("test", "free", "trial", "paid", "lifetime"), selected = type, onSelect = { type = it })
                Spacer(Modifier.height(8.dp))
                DropdownSelector(label = "Tier", options = listOf("trial", "starter", "early_adopters", "professional", "business", "agency", "dev"), selected = tier, onSelect = { tier = it })
                Spacer(Modifier.height(8.dp))
                OutlinedTextField(value = maxSites, onValueChange = { maxSites = it }, label = { Text("Max Sites") }, singleLine = true, modifier = Modifier.fillMaxWidth())
                Spacer(Modifier.height(8.dp))
                OutlinedTextField(value = apiLimit, onValueChange = { apiLimit = it }, label = { Text("API Limit (0 = tier default)") }, singleLine = true, modifier = Modifier.fillMaxWidth())
                Spacer(Modifier.height(8.dp))
                OutlinedTextField(value = expiresDays, onValueChange = { expiresDays = it }, label = { Text("Expires (days, blank = never)") }, singleLine = true, modifier = Modifier.fillMaxWidth())
                Spacer(Modifier.height(8.dp))
                OutlinedTextField(value = assignedTo, onValueChange = { assignedTo = it }, label = { Text("Assigned To (email)") }, singleLine = true, modifier = Modifier.fillMaxWidth())
                Spacer(Modifier.height(8.dp))
                OutlinedTextField(value = notes, onValueChange = { notes = it }, label = { Text("Notes") }, modifier = Modifier.fillMaxWidth())
            }
        },
        confirmButton = {
            Button(onClick = {
                onGenerate(
                    GenerateLicenseRequest(
                        count = count.toIntOrNull()?.coerceIn(1, 100) ?: 1,
                        type = type,
                        tier = tier,
                        maxSites = maxSites.toIntOrNull() ?: 1,
                        apiCallsLimit = apiLimit.toIntOrNull() ?: 0,
                        expiresDays = expiresDays.toIntOrNull(),
                        assignedTo = assignedTo,
                        notes = notes,
                    )
                )
            }) { Text("Generate") }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Cancel") } },
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DropdownSelector(label: String, options: List<String>, selected: String, onSelect: (String) -> Unit) {
    var expanded by remember { mutableStateOf(false) }
    ExposedDropdownMenuBox(expanded = expanded, onExpandedChange = { expanded = it }) {
        OutlinedTextField(
            value = selected,
            onValueChange = {},
            readOnly = true,
            label = { Text(label) },
            trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded) },
            modifier = Modifier.fillMaxWidth().menuAnchor(),
        )
        ExposedDropdownMenu(expanded = expanded, onDismissRequest = { expanded = false }) {
            options.forEach { opt ->
                DropdownMenuItem(text = { Text(opt) }, onClick = { onSelect(opt); expanded = false })
            }
        }
    }
}

@Composable
private fun RevokeDialog(license: License, onDismiss: () -> Unit, onConfirm: (String) -> Unit) {
    var reason by remember { mutableStateOf("") }
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Revoke License") },
        text = {
            Column {
                Text("Revoke ${license.licenseKey.take(24)}…?")
                Spacer(Modifier.height(8.dp))
                OutlinedTextField(value = reason, onValueChange = { reason = it }, label = { Text("Reason") }, modifier = Modifier.fillMaxWidth())
            }
        },
        confirmButton = { Button(onClick = { onConfirm(reason.ifBlank { "Revoked via admin app" }) }) { Text("Revoke") } },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Cancel") } },
    )
}
