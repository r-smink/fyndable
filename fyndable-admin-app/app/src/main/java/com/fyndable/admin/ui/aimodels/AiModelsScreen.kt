package com.fyndable.admin.ui.aimodels

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ExposedDropdownMenuBox
import androidx.compose.material3.ExposedDropdownMenuDefaults
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import com.fyndable.admin.data.remote.AiModelsResponse
import com.fyndable.admin.data.remote.SaveAiModelsRequest
import com.fyndable.admin.data.repo.ApiResult
import com.fyndable.admin.data.repo.AiModelRepository
import com.fyndable.admin.ui.components.ErrorView
import com.fyndable.admin.ui.components.LoadingIndicator
import com.fyndable.admin.ui.components.SectionHeader
import dagger.hilt.android.lifecycle.HiltViewModel
import javax.inject.Inject
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

sealed class AiModelsState {
    data object Loading : AiModelsState()
    data class Success(val data: AiModelsResponse) : AiModelsState()
    data class Error(val message: String) : AiModelsState()
}

@HiltViewModel
class AiModelsViewModel @Inject constructor(
    private val repository: AiModelRepository,
) : ViewModel() {

    private val _state = MutableStateFlow<AiModelsState>(AiModelsState.Loading)
    val state = _state.asStateFlow()

    var saving by mutableStateOf(false)
        private set

    fun load() {
        _state.value = AiModelsState.Loading
        viewModelScope.launch {
            when (val result = repository.get()) {
                is ApiResult.Success -> _state.value = AiModelsState.Success(result.data)
                is ApiResult.Error -> _state.value = AiModelsState.Error(result.message)
            }
        }
    }

    fun refresh() {
        viewModelScope.launch {
            repository.refresh()
            load()
        }
    }

    fun save(standard: Map<String, String>, premium: Map<String, String>, onDone: (Boolean) -> Unit) {
        saving = true
        viewModelScope.launch {
            when (val result = repository.save(SaveAiModelsRequest(standard, premium))) {
                is ApiResult.Success -> { saving = false; load(); onDone(true) }
                is ApiResult.Error -> { saving = false; onDone(false) }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AiModelsScreen(viewModel: AiModelsViewModel = hiltViewModel()) {
    val state by viewModel.state.collectAsStateWithLifecycle()

    LaunchedEffect(Unit) { if (state is AiModelsState.Loading) viewModel.load() }

    Scaffold(topBar = {
        TopAppBar(
            title = { Text("AI Models") },
            actions = {
                TextButton(onClick = { viewModel.refresh() }) { Text("Refresh") }
            },
        )
    }) { padding ->
        when (val s = state) {
            is AiModelsState.Loading -> LoadingIndicator(Modifier.padding(padding))
            is AiModelsState.Error -> ErrorView(s.message, { viewModel.load() }, Modifier.padding(padding))
            is AiModelsState.Success -> AiModelsContent(s.data, viewModel, Modifier.padding(padding))
        }
    }
}

@Composable
private fun AiModelsContent(
    data: AiModelsResponse,
    viewModel: AiModelsViewModel,
    modifier: Modifier,
) {
    // Local editable copies of routing maps
    var standardRouting by remember(data) { mutableStateOf(data.standard.routing.ifEmpty { data.standard.defaults }) }
    var premiumRouting by remember(data) { mutableStateOf(data.premium.routing.ifEmpty { data.premium.defaults }) }

    Column(
        modifier = modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp),
    ) {
        Text(
            "${data.allModelCount} models available",
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        Spacer(Modifier.height(16.dp))

        SectionHeader("Standard Tier (Starter)")
        data.useCases.forEach { (key, label) ->
            ModelSelector(
                label = label,
                models = data.standard.models,
                selected = standardRouting[key] ?: data.standard.defaults[key] ?: "",
                onSelect = { standardRouting = standardRouting + (key to it) },
            )
            Spacer(Modifier.height(8.dp))
        }

        Spacer(Modifier.height(16.dp))
        SectionHeader("Premium Tier (Professional+)")
        data.useCases.forEach { (key, label) ->
            ModelSelector(
                label = label,
                models = data.premium.models,
                selected = premiumRouting[key] ?: data.premium.defaults[key] ?: "",
                onSelect = { premiumRouting = premiumRouting + (key to it) },
            )
            Spacer(Modifier.height(8.dp))
        }

        Spacer(Modifier.height(16.dp))
        Button(
            onClick = { viewModel.save(standardRouting, premiumRouting) {} },
            enabled = !viewModel.saving,
            modifier = Modifier.fillMaxWidth(),
        ) {
            Text(if (viewModel.saving) "Saving…" else "Save AI Models")
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun ModelSelector(
    label: String,
    models: Map<String, String>,
    selected: String,
    onSelect: (String) -> Unit,
) {
    var expanded by remember { mutableStateOf(false) }
    val displayLabel = models[selected] ?: selected

    ExposedDropdownMenuBox(expanded = expanded, onExpandedChange = { expanded = it }) {
        OutlinedTextField(
            value = displayLabel,
            onValueChange = {},
            readOnly = true,
            label = { Text(label) },
            trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded) },
            modifier = Modifier.fillMaxWidth().menuAnchor(),
        )
        ExposedDropdownMenu(expanded = expanded, onDismissRequest = { expanded = false }) {
            models.entries.forEach { (modelKey, modelLabel) ->
                DropdownMenuItem(
                    text = { Text(modelLabel) },
                    onClick = { onSelect(modelKey); expanded = false },
                )
            }
            // Ensure the currently saved model is always selectable even if not in the list
            if (selected.isNotBlank() && !models.containsKey(selected)) {
                DropdownMenuItem(
                    text = { Text("$selected (saved)") },
                    onClick = { onSelect(selected); expanded = false },
                )
            }
        }
    }
}
