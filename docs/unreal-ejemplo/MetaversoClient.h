// Ejemplo mínimo: cliente Unreal del Metaverso Escolar TecNM.
// Hace el flujo LTI: canjea el lti_session_token y envía la calificación.
// Copia este archivo a Source/<TuProyecto>/ en tu proyecto C++ de Unreal.
#pragma once

#include "CoreMinimal.h"
#include "GameFramework/Actor.h"
#include "MetaversoClient.generated.h"

UCLASS()
class AMetaversoClient : public AActor
{
    GENERATED_BODY()

public:
    AMetaversoClient();

    // URL del backend (Unreal corre en la misma Mac -> localhost).
    UPROPERTY(EditAnywhere, BlueprintReadWrite, Category = "Metaverso")
    FString BaseUrl = TEXT("http://localhost:8000");

    // Pega aquí el token del deeplink (tecnm-metaverso://play?lti_session_token=XXXX),
    // o pásalo al ejecutable con  -LtiToken=XXXX
    UPROPERTY(EditAnywhere, BlueprintReadWrite, Category = "Metaverso")
    FString LtiSessionToken;

    // Calificación 0-100 que el "juego" reporta al terminar.
    UPROPERTY(EditAnywhere, BlueprintReadWrite, Category = "Metaverso")
    float Calificacion = 90.f;

    // Llama esto desde Blueprint o desde un botón para correr el flujo.
    UFUNCTION(BlueprintCallable, Category = "Metaverso")
    void JugarYCompletar();

protected:
    virtual void BeginPlay() override;

private:
    void RedeemToken(const FString& Token);
    void CompleteSession(int32 IdSesion, const FString& AccessToken);
};
