// Subsystem del Metaverso Escolar TecNM para integrarlo en la JUGABILIDAD.
// Accesible desde cualquier Blueprint: "Get Game Instance Subsystem -> Metaverso Subsystem".
// Flujo: IniciarSesion (al empezar el nivel) -> SumarAcierto (durante el juego)
//        -> Terminar (al acabar) envía la calificación a Moodle (vía el backend/AGS).
// Copia este archivo a Source/<TuProyecto>/ y agrega "HTTP","Json","JsonUtilities" en el .Build.cs
#pragma once

#include "CoreMinimal.h"
#include "Subsystems/GameInstanceSubsystem.h"
#include "MetaversoSubsystem.generated.h"

DECLARE_DYNAMIC_MULTICAST_DELEGATE_OneParam(FMetaversoMensaje, const FString&, Mensaje);

UCLASS()
class UMetaversoSubsystem : public UGameInstanceSubsystem
{
    GENERATED_BODY()

public:
    virtual void Initialize(FSubsystemCollectionBase& Collection) override;

    // URL del backend (Unreal en la misma Mac -> localhost).
    UPROPERTY(BlueprintReadWrite, Category = "Metaverso")
    FString BaseUrl = TEXT("http://localhost:8000");

    // Token leído de la línea de comandos (-LtiToken=XXXX). También puedes pasar el token a IniciarSesion().
    UPROPERTY(BlueprintReadOnly, Category = "Metaverso")
    FString TokenInicial;

    UPROPERTY(BlueprintReadOnly, Category = "Metaverso")
    bool bSesionLista = false;

    UPROPERTY(BlueprintReadOnly, Category = "Metaverso")
    int32 IdSesion = 0;

    // Contador de aciertos del juego (lo sube SumarAcierto).
    UPROPERTY(BlueprintReadOnly, Category = "Metaverso")
    int32 Aciertos = 0;

    // Eventos para enganchar la UI / Blueprints.
    UPROPERTY(BlueprintAssignable, Category = "Metaverso")
    FMetaversoMensaje OnSesionLista;
    UPROPERTY(BlueprintAssignable, Category = "Metaverso")
    FMetaversoMensaje OnCalificacionEnviada;
    UPROPERTY(BlueprintAssignable, Category = "Metaverso")
    FMetaversoMensaje OnError;

    // Llama al INICIAR el nivel. Si pasas cadena vacía usa TokenInicial (de -LtiToken).
    UFUNCTION(BlueprintCallable, Category = "Metaverso")
    void IniciarSesion(const FString& LtiSessionToken);

    // Llama en cada objetivo logrado del juego (recoger moneda, acertar, etc.).
    UFUNCTION(BlueprintCallable, Category = "Metaverso")
    void SumarAcierto();

    // Llama al TERMINAR. Calcula calificación = Aciertos/TotalActividades*100 y la envía.
    UFUNCTION(BlueprintCallable, Category = "Metaverso")
    void Terminar(int32 TotalActividades);

    // Variante: enviar una calificación 0-100 directa (si calculas el puntaje tú mismo).
    UFUNCTION(BlueprintCallable, Category = "Metaverso")
    void EnviarCalificacion(float Calificacion);

private:
    FString AccessToken;
};
