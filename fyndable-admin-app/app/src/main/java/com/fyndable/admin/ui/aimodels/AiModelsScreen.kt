package com.fyndable.admin.ui.aimodels

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AutoAwesome
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ExposedDropdownMenuBox
import androidx.compose.material3.ExposedDropdownMenuDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
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
import com.fyndable.admin.ui.components.OpsHeader
import com.fyndable.admin.ui.components.SectionHeader
import com.fyndable.admin.ui.components.TopBarAction
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

    Scaffold(
        topBar = {
            OpsHeader(
                title = "AI Models",
                actions = {
                    TopBarAction(icon = Icons.Filled.Refresh, contentDescription = "Refresh", onClick = { viewModel.refresh() })
                }
            )
        },
        containerColor = Color(0xFFF8FAFC)
    ) { padding ->
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
    var standardRouting by remember(data) { mutableStateOf(data.standard.routing.ifEmpty { data.standard.defaults }) }
    var premiumRouting by remember(data) { mutableStateOf(data.premium.routing.ifEmpty { data.premium.defaults }) }

    val gradient = Brush.horizontalGradient(
        colors = listOf(Color(0xFF6366F1), Color(0xFF8B5CF6))
    )

    Column(
        modifier = modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp),
    ) {
        // Spend Card
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(20.dp),
            elevation = CardDefaults.cardElevation(defaultElevation = 4.dp)
        ) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(gradient)
                    .padding(24.dp)
            ) {
                Column {
                    Text(
                        text = "AI SPEND THIS MONTH",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Black,
                        color = Color.White.copy(alpha = 0.7f),
                        letterSpacing = 1.sp
                    )
                    Spacer(Modifier.height(4.dp))
                    Text(
                        text = "€1,842.60",
                        fontSize = 32.sp,
                        fontWeight = FontWeight.Black,
                        color = Color.White
                    )
                    Spacer(Modifier.height(12.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Filled.AutoAwesome, contentDescription = null, tint = Color.White, modifier = Modifier.size(14.dp))
                        Spacer(Modifier.height(4.dp))
                        Text(
                            text = "${data.allModelCount} Models Connected",
                            fontSize = 12.sp,
                            color = Color.White.copy(alpha = 0.9f),
                            fontWeight = FontWeight.Medium
                        )
                    }
                }
            }
        }

        Spacer(Modifier.height(24.dp))
        
        SectionHeader("Model Configuration")
        
        Text(
            text = "Assign AI models to specific tasks per subscription tier.",
            style = MaterialTheme.typography.bodySmall,
            color = Color(0xFF64748B),
            modifier = Modifier.padding(bottom = 16.dp)
        )

        SectionHeader("Standard Tier (Starter)", modifier = Modifier.padding(vertical = 8.dp))
        data.useCases.forEach { (key, label) ->
            TaskCard(
                label = label,
                models = data.standard.models,
                selected = standardRouting[key] ?: data.standard.defaults[key] ?: "",
                onSelect = { standardRouting = standardRouting + (key to it) }
            )
            Spacer(Modifier.height(12.dp))
        }

        Spacer(Modifier.height(16.dp))
        SectionHeader("Premium Tier (Pro/Agency)", modifier = Modifier.padding(vertical = 8.dp))
        data.useCases.forEach { (key, label) ->
            TaskCard(
                label = label,
                models = data.premium.models,
                selected = premiumRouting[key] ?: data.premium.defaults[key] ?: "",
                onSelect = { premiumRouting = premiumRouting + (key to it) }
            )
            Spacer(Modifier.height(12.dp))
        }

        Spacer(Modifier.height(24.dp))
        Button(
            onClick = { viewModel.save(standardRouting, premiumRouting) {} },
            enabled = !viewModel.saving,
            modifier = Modifier
                .fillMaxWidth()
                .height(56.dp),
            shape = RoundedCornerShape(12.dp),
            colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF6366F1))
        ) {
            Text(
                text = if (viewModel.saving) "Updating Models..." else "Save Changes",
                fontWeight = FontWeight.Bold,
                fontSize = 16.sp
            )
        }
        Spacer(Modifier.height(32.dp))
    }
}

@Composable
private fun TaskCard(
    label: String,
    models: Map<String, String>,
    selected: String,
    onSelect: (String) -> Unit,
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        border = BorderStroke(1.dp, Color(0xFFF1F5F9))
    ) {
        Column(Modifier.padding(16.dp)) {
            Text(
                text = label.uppercase(),
                fontSize = 10.sp,
                fontWeight = FontWeight.Black,
                color = Color(0xFF94A3B8),
                letterSpacing = 0.5.sp
            )
            Spacer(Modifier.height(8.dp))
            ModelSelector(
                models = models,
                selected = selected,
                onSelect = onSelect
            )
            Spacer(Modifier.height(8.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(modifier = Modifier.size(6.dp).background(Color(0xFF10B981), RoundedCornerShape(3.dp)))
                Spacer(Modifier.padding(horizontal = 4.dp))
                Text(
                    text = "Est. cost: €0.012 / call",
                    fontSize = 11.sp,
                    color = Color(0xFF64748B),
                    fontWeight = FontWeight.Medium
                )
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun ModelSelector(
    models: Map<String, String>,
    selected: String,
    onSelect: (String) -> Unit,
) {
    var expanded by remember { mutableStateOf(false) }
    val displayLabel = models[selected] ?: selected

    ExposedDropdownMenuBox(
        expanded = expanded,
        onExpandedChange = { expanded = it }
    ) {
        OutlinedTextField(
            value = displayLabel,
            onValueChange = {},
            readOnly = true,
            trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded) },
            modifier = Modifier.fillMaxWidth().menuAnchor(),
            shape = RoundedCornerShape(8.dp),
            colors = OutlinedTextFieldDefaults.colors(
                focusedTextColor = Color(0xFF1E293B),
                unfocusedTextColor = Color(0xFF1E293B),
                focusedBorderColor = Color(0xFF6366F1),
                unfocusedBorderColor = Color(0xFFE2E8F0),
                focusedContainerColor = Color(0xFFF8FAFC),
                unfocusedContainerColor = Color(0xFFF8FAFC)
            ),
            textStyle = MaterialTheme.typography.bodyMedium.copy(fontWeight = FontWeight.Bold)
        )
        ExposedDropdownMenu(expanded = expanded, onDismissRequest = { expanded = false }) {
            models.entries.forEach { (modelKey, modelLabel) ->
                DropdownMenuItem(
                    text = { Text(modelLabel) },
                    onClick = { onSelect(modelKey); expanded = false },
                )
            }
        }
    }
}
