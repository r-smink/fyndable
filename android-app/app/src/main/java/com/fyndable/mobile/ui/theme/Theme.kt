package com.fyndable.mobile.ui.theme

import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

private val FyndableColorScheme = lightColorScheme(
    primary = FyndablePurple,
    onPrimary = Color.White,
    primaryContainer = FyndablePurpleTint,
    onPrimaryContainer = FyndablePurpleDark,
    secondary = FyndableBlue,
    onSecondary = Color.White,
    secondaryContainer = FyndableBlueTint,
    onSecondaryContainer = FyndableBlueDark,
    tertiary = FyndableMagenta,
    onTertiary = Color.White,
    tertiaryContainer = FyndableMagentaLight,
    onTertiaryContainer = FyndableMagenta,
    background = LightBackground,
    onBackground = LightOnSurface,
    surface = LightSurface,
    onSurface = LightOnSurface,
    surfaceVariant = LightSurfaceVariant,
    onSurfaceVariant = LightOnSurfaceVariant,
    outline = Gray200,
    outlineVariant = Gray100,
    error = DangerRed,
    onError = Color.White,
    errorContainer = DangerRedLight,
    onErrorContainer = DangerRed,
)

@Composable
fun FyndableTheme(
    darkTheme: Boolean = false, // Design is a light, branded UI
    dynamicColor: Boolean = false, // Disabled to keep Fyndable brand colors
    content: @Composable () -> Unit
) {
    MaterialTheme(
        colorScheme = FyndableColorScheme,
        typography = FyndableTypography,
        content = content
    )
}
