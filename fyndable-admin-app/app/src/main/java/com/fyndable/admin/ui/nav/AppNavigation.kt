package com.fyndable.admin.ui.nav

import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Build
import androidx.compose.material.icons.filled.Dashboard
import androidx.compose.material.icons.filled.Key
import androidx.compose.material.icons.filled.Map
import androidx.compose.material.icons.filled.ModelTraining
import androidx.compose.material.icons.filled.SupportAgent
import androidx.compose.material.icons.filled.Analytics
import androidx.compose.material3.Icon
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.vector.ImageVector

sealed class Screen(val route: String, val label: String, val icon: ImageVector) {
    data object Dashboard : Screen("dashboard", "Dashboard", Icons.Filled.Dashboard)
    data object Licenses : Screen("licenses", "Licenses", Icons.Filled.Key)
    data object GeoScan : Screen("geo-scan", "GEO Scan", Icons.Filled.Map)
    data object Support : Screen("support", "Support", Icons.Filled.SupportAgent)
    data object Usage : Screen("usage", "Usage", Icons.Filled.Analytics)
    data object AiModels : Screen("ai-models", "AI Models", Icons.Filled.ModelTraining)
}

val bottomNavItems = listOf(
    Screen.Dashboard,
    Screen.Licenses,
    Screen.GeoScan,
    Screen.Support,
    Screen.Usage,
    Screen.AiModels,
)

@Composable
fun BottomNavBar(currentRoute: String?, onNavigate: (String) -> Unit) {
    NavigationBar {
        bottomNavItems.forEach { screen ->
            NavigationBarItem(
                selected = currentRoute == screen.route,
                onClick = { onNavigate(screen.route) },
                icon = { Icon(screen.icon, contentDescription = screen.label) },
                label = { Text(screen.label) },
            )
        }
    }
}
